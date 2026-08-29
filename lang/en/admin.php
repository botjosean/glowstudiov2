<?php

return [
    'invalidTransition' => 'This appointment can no longer be changed.',
    'invalidService' => 'That service is not available.',
    'phoneInvalid' => 'The phone must have 10 digits (or leave it empty).',
    'publishBlockedNoServices' => 'Add at least one active service to publish your profile.',
    'contentNeedsPhotos' => 'That model needs :count photos.',
    'contentVideoOnlyReference' => 'A video can only be saved as a reference, not used to build a post.',
    'contentOneVideoAtATime' => 'Upload the video on its own, one at a time and without photos.',
    'contentVideoType' => "That video format won't work. Try one recorded on your phone (MP4 or MOV).",
    'contentVideoTooBig' => 'That video is too heavy. Trim it or lower the quality — 50 MB is the limit.',
    'contentPhotoTooBig' => 'One of the photos is too heavy. The limit is 8 MB per photo.',
    'contentUploadFailed' => 'The upload was cut off before it finished. Try again, and if it is a video, keep it under 50 MB.',
    // Stored as the block's reason, so the provider sees it in their list.
    'timeOffPauseReason' => 'Agenda closed',
    // Sent to the client via Kapso when the provider's WhatsApp is connected —
    // mirrors resources/js/i18n/*.json waMessage* exactly (that copy is only
    // ever shown, never sent, when there's no bot to send it automatically).
    'waMessageConfirmed' => 'Hi :client! This is :provider. Your :service appointment on :date is confirmed. See you then!',
    'waMessageRejected' => "Hi :client! This is :provider. I can't take your :service request for :date. Message me and we'll find another time.",
    'waMessageReviewAsk' => 'Hi :client! This is :provider. Thanks for coming in today 💛 Would you leave me a review? It is one tap: :link',
    'waMessageRescheduled' => 'Hi :client! This is :provider. I moved your :service appointment: it is now on :date. Message me if that does not work.',
    'waMessageCancelled' => "Hi :client! This is :provider. I had to cancel your :service appointment on :date. Sorry about that, message me and we'll reschedule.",
];
