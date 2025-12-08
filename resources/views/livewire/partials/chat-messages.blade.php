@forelse ($messages ?? [] as $msg)
    <article class="flex items-start gap-3 mb-4 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
        <div class="h-8 w-8 shrink-0 rounded-full grid place-items-center {{ $msg['role'] === 'user' ? 'bg-violet-300 text-white' : 'bg-gradient-to-br from-violet-100 to-indigo-100' }}">
            @if($msg['role'] === 'user')
                👤
            @else
                <svg class="w-5 h-5" viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg">
                  <g transform="translate(-250, -250)">
                    <path fill="#7b46e4" d="M643.44,396.31c31.82,0,57.61-25.83,57.61-57.69s-25.79-57.69-57.61-57.69-57.61,25.83-57.61,57.69,25.79,57.69,57.61,57.69Z"/>
                    <path fill="#252525" d="M582.94,580.38c-2.85,3.18-8.56,9.08-17.19,14.68-3.67,2.38-7.52,4.49-11.54,6.3-10.99,4.95-23.05,7.45-35.83,7.45s-24.87-2.49-36.03-7.41c-11.18-4.94-20.89-12.04-28.88-21.09-7.85-8.89-14.06-19.61-18.48-31.85-4.41-12.11-6.62-25.61-6.62-40.13s2.24-27.36,6.64-39.31c4.45-12.1,10.74-22.49,18.67-30.87,7.97-8.37,17.65-15.01,28.78-19.73,11.13-4.7,23.55-7.04,35.92-7.04v-109.14c-31.34,0-60.81,4.9-87.61,14.57-26.66,9.64-49.99,23.5-69.37,41.21-19.3,17.66-34.72,39.43-45.77,64.7-11.06,25.27-16.68,54.08-16.68,85.61s5.61,60.7,16.71,86.56c11.06,25.84,26.49,48.2,45.85,66.47,19.4,18.27,42.74,32.6,69.37,42.59,26.75,10.02,56.19,15.11,87.51,15.11s60.76-5.09,87.51-15.11c26.63-9.98,50.07-24.32,69.69-42.62,1.28-1.19,10.58-10.59,22.21-23.33,1.42-1.56,2.57-2.82,3.26-3.59-36.82-21.33-73.64-42.67-110.46-64-1.75,2.67-4.26,6.17-7.66,9.97Z"/>
                  </g>
                </svg>
            @endif
        </div>
        <div class="flex-1 {{ $msg['role'] === 'user' ? 'text-right' : '' }}">
            <div class="text-xs text-gray-500 mb-1">
                {{ $msg['role'] === 'user' ? 'Vous' : 'Tolery' }}
                <span class="mx-1">•</span>
                <time>{{ \Illuminate\Support\Carbon::parse($msg['created_at'] ?? now())->format('H:i') }}</time>
            </div>
            <div class="{{ $msg['role'] === 'user' ? 'inline-block border border-gray-100 bg-gray-50' : 'inline-block bg-gray-100 text-gray-900' }} rounded-xl px-3 py-2">
                {!! nl2br(e($msg['content'] ?? '')) !!}
            </div>
        </div>
    </article>
@empty
@endforelse
