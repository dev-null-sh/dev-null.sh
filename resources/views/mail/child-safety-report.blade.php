<x-mail::message>
A child safety report is waiting for review on /dev/null. It's at the top of the moderation queue.

<x-mail::button :url="$url">
Open the queue
</x-mail::button>

<x-mail::subcopy>
You're getting this because you moderate /dev/null. The details are only in the queue.
</x-mail::subcopy>
</x-mail::message>
