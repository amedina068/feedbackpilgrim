<?php

namespace App\Http\Controllers;

use App\Support\ApiDocs\LlmsTxtGenerator;
use App\Support\ApiDocs\SkillFileGenerator;
use Illuminate\Http\Response;

/**
 * Machine-readable discovery for agents. Both files render on each request.
 */
class AgentDocsController extends Controller
{
    public function llms(): Response
    {
        return response((new LlmsTxtGenerator)->toText(url('/')), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function skill(): Response
    {
        return response((new SkillFileGenerator)->toMarkdown(url('/')), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
        ]);
    }
}
