<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Description('Send a test email through the configured mailer, to prove that the mail settings work. The default recipient is the first owner.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld]
class SendTestEmailTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'send-test-email';

    public function handle(Request $request): Response
    {
        return $this->audited($request, function () use ($request) {
            $request->validate(['to' => 'nullable|string|email']);

            $to = $request->get('to') ?? User::query()->where('is_owner', true)->orderBy('id')->value('email');

            if (! $to) {
                return Response::error('Give to. The instance has no owner to send to.');
            }

            $mailer = (string) config('mail.default');

            try {
                Mail::mailer($mailer)->raw(
                    'This is a test email from Feedback Pilgrim at '.config('app.url').'. The mail settings work.',
                    fn (Message $message) => $message->to($to)->subject('Feedback Pilgrim test email'),
                );
            } catch (Throwable $exception) {
                return Response::error("The {$mailer} mailer could not send the email: ".strtok($exception->getMessage(), "\n"));
            }

            return Response::json(['sent' => true, 'to' => $to, 'mailer' => $mailer]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'to' => $schema->string()->description('The recipient. Default: the first owner.'),
        ];
    }
}
