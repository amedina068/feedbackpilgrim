@php($rows = $rows ?? [])
@if (count($rows))
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full border-collapse text-sm">
            <tbody class="divide-y divide-gray-100">
                @foreach ($rows as $row)
                    <tr class="align-top">
                        <td class="w-1/3 py-3 pl-4 pr-3">
                            <code class="font-mono text-[13px] font-semibold text-gray-900">{{ $row['name'] }}</code>
                            @if ($row['required'])
                                <span class="ml-1.5 align-middle text-[10px] font-semibold uppercase tracking-wider text-rose-600">required</span>
                            @endif
                        </td>
                        <td class="py-3 pl-3 pr-4">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-mono text-xs text-violet-700">{{ $row['type'] }}</span>
                                @if (! empty($row['nullable']))
                                    <span class="text-[11px] text-gray-400">nullable</span>
                                @endif
                            </div>
                            @if (! empty($row['enum']))
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach ($row['enum'] as $value)
                                        <code class="rounded-sm bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-700">{{ $value }}</code>
                                    @endforeach
                                </div>
                            @endif
                            @if (! empty($row['description']))
                                <p class="mt-1.5 leading-relaxed text-gray-500">{{ $row['description'] }}</p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
