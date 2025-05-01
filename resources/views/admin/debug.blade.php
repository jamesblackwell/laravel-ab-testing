@extends('ab-testing::layouts.admin')

@section('header')
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('A/B Testing Debugger') }}
        </h2>
        <a href="{{ route('ab-testing.admin.index') }}"
            class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
            &larr; Back to Dashboard
        </a>
    </div>
@endsection

@section('content')
    <div class="bg-white dark:bg-gray-800 sm:rounded-lg shadow overflow-hidden p-6 space-y-6">
        <p class="text-gray-600 dark:text-gray-400">
            Use this tool to inspect the cache entries and Pennant feature assignment for a specific experiment and scope
            (User ID or Anonymous ID).
        </p>

        <form method="GET" action="{{ route('ab-testing.debug') }}" class="space-y-4">
            <div>
                <label for="experiment_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Experiment
                    Name</label>
                <input type="text" name="experiment_name" id="experiment_name" list="experiment_list"
                    value="{{ $inputExperimentName ?? '' }}" placeholder="e.g., homepage-cta-test"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <datalist id="experiment_list">
                    @foreach ($distinctExperiments as $name)
                        <option value="{{ $name }}">
                    @endforeach
                </datalist>
            </div>

            <div>
                <label for="scope_identifier" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Scope
                    Identifier (User ID or Anonymous ID)</label>
                <input type="text" name="scope_identifier" id="scope_identifier"
                    value="{{ $inputScopeIdentifier ?? '' }}" placeholder="e.g., 123 or anon_xyz789"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>

            <div>
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                    Lookup Data
                </button>
            </div>
        </form>
    </div>

    @if ($lookupData)
        <div class="mt-8 bg-white dark:bg-gray-800 sm:rounded-lg shadow overflow-hidden p-6 space-y-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                Lookup Results for '{{ $inputExperimentName }}' and Scope '{{ $inputScopeIdentifier }}'
            </h3>

            {{-- Cache Data --}}
            <div>
                <h4 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-2">Cache Entries</h4>
                <div class="space-y-2 text-sm">
                    @foreach ($lookupData['cache'] as $type => $data)
                        <div>
                            <span
                                class="font-mono bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs mr-2">{{ $type }}</span>
                            <span class="font-mono text-gray-500 dark:text-gray-400">({{ $data['key'] }})</span>:
                            @if ($data['exists'])
                                <span class="ml-2 text-green-600 dark:text-green-400 font-semibold">Found</span>
                                <span class="ml-2">Value: <code
                                        class="font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded">{{ var_export($data['value'], true) }}</code></span>
                            @else
                                <span class="ml-2 text-red-600 dark:text-red-400 font-semibold">Not Found</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Pennant Feature Data --}}
            <div>
                <h4 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-2">Pennant Feature Assignment</h4>
                <div class="space-y-1 text-sm">
                    <div>
                        <span class="font-medium">Scope Searched:</span>
                        <code
                            class="ml-2 font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded">{{ $lookupData['feature']['scope_searched'] }}</code>
                    </div>
                    @if ($lookupData['feature']['record_found'])
                        <div>
                            <span class="font-medium">Status:</span>
                            <span class="ml-2 text-green-600 dark:text-green-400 font-semibold">Record Found</span>
                        </div>
                        <div>
                            <span class="font-medium">Assigned Value:</span>
                            <code
                                class="ml-2 font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded">{{ var_export($lookupData['feature']['value'], true) }}</code>
                        </div>
                        <div>
                            <span class="font-medium">Assigned At:</span>
                            <span
                                class="ml-2">{{ $lookupData['feature']['assigned_at'] ? $lookupData['feature']['assigned_at']->format('Y-m-d H:i:s') : 'N/A' }}</span>
                        </div>
                    @else
                        <div>
                            <span class="font-medium">Status:</span>
                            <span class="ml-2 text-red-600 dark:text-red-400 font-semibold">Record Not Found</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @elseif(request()->has('experiment_name') && request()->has('scope_identifier'))
        {{-- Show message if form submitted but no data found (e.g., invalid inputs) --}}
        <div class="mt-8 bg-white dark:bg-gray-800 sm:rounded-lg shadow overflow-hidden p-6">
            <p class="text-center text-gray-500 dark:text-gray-400">No lookup data generated. Please ensure the experiment
                name and scope identifier are correct.</p>
        </div>
    @endif

@endsection
