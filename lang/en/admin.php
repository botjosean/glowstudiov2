<?php

return [
    'invalidTransition' => 'This appointment can no longer be changed.',
    'invalidService' => 'That service is not available.',
    'phoneInvalid' => 'The phone must have 10 digits (or leave it empty).',
    'publishBlockedNoServices' => 'Add at least one active service to publish your profile.',
    // Stored as the block's reason, so the provider sees it in their list.
    'timeOffPauseReason' => 'Agenda closed',
    // Sent to the client via Kapso when the provider's WhatsApp is connected —
    // mirrors resources/js/i18n/*.json waMessage* exactly (that copy is only
    // ever shown, never sent, when there's no bot to send it automatically).
    'waMessageConfirmed' => 'Hi :client! This is :provider. Your :service appointment on :date is confirmed. See you then!',
    'waMessageRejected' => "Hi :client! This is :provider. I can't take your :service request for :date. Message me and we'll find another time.",
    'waMessageCancelled' => "Hi :client! This is :provider. I had to cancel your :service appointment on :date. Sorry about that, message me and we'll reschedule.",
];
