@if (session('status'))
    <div
        class="mb-4 text-[#108500] text-[10px] md:text-[11px] font-bold uppercase p-3 bg-green-50 rounded-lg border border-green-100">
        {{ session('status') }}
    </div>
@endif
@if (session('error'))
    <div
        class="mb-4 text-red-600 text-[10px] md:text-[11px] font-bold uppercase p-3 bg-red-50 rounded-lg border border-red-200">
        {{ session('error') }}
    </div>
@endif
@if (session('warning'))
    <div
        class="mb-4 text-[#b8860b] text-[10px] md:text-[11px] font-bold uppercase p-3 bg-yellow-50 rounded-lg border border-yellow-200">
        {{ session('warning') }}
    </div>
@endif
