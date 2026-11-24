@php($dkimNumber = 0)
<div class="space-y-4">
    <p class="rounded-md bg-white/5 p-3 text-sm text-fp-mid ring-1 ring-inset ring-white/5">Some DNS providers add your domain to the name for you. If yours does, enter only the part before your domain, for example <code class="font-mono text-xs text-fp-hi">abc123._domainkey</code>.</p>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-xs text-fp-low">
                <tr>
                    <th scope="col" class="whitespace-nowrap py-2 pr-3 font-medium">Type</th>
                    <th scope="col" class="whitespace-nowrap py-2 pr-3 font-medium">Name</th>
                    <th scope="col" class="whitespace-nowrap py-2 font-medium">Value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @foreach ($sendingDomain->dnsRecords() as $record)
                    @php($recordLabel = $record['purpose'] === 'DKIM' ? 'DKIM record '.(++$dkimNumber) : "{$record['purpose']} {$record['type']} record")
                    <tr class="align-top">
                        <td class="py-3 pr-3">
                            <span class="font-mono text-xs text-fp-hi">{{ $record['type'] }}</span>
                            <span class="mt-0.5 block whitespace-nowrap text-xs text-fp-low">{{ $record['purpose'] }}{{ $record['purpose'] === 'DMARC' ? ', suggested' : '' }}</span>
                        </td>
                        <td class="py-3 pr-3">
                            <div class="flex items-start gap-x-2">
                                <code v-pre class="min-w-0 flex-1 break-all font-mono text-xs text-fp-hi">{{ $record['name'] }}</code>
                                <copy-button text="{{ $record['name'] }}" aria-label="Copy {{ $recordLabel }} name"></copy-button>
                            </div>
                        </td>
                        <td class="py-3">
                            <div class="flex items-start gap-x-2">
                                <code v-pre class="min-w-0 flex-1 break-all font-mono text-xs text-fp-hi">{{ $record['value'] }}</code>
                                <copy-button text="{{ $record['value'] }}" aria-label="Copy {{ $recordLabel }} value"></copy-button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-sm text-fp-mid">The DMARC record is a suggestion. We do not check it, and your emails send without it. Gmail and Yahoo require DMARC for bulk senders.</p>
</div>
