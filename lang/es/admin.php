<?php

return [
    'invalidTransition' => 'Esta cita ya no se puede modificar.',
    'invalidService' => 'Ese servicio no está disponible.',
    'phoneInvalid' => 'El teléfono debe tener 10 dígitos (o déjalo vacío).',
    'publishBlockedNoServices' => 'Añade al menos un servicio activo para publicar tu perfil.',
    'contentNeedsPhotos' => 'Hacen falta :count fotos para armar ese modelo.',
    'contentVideoOnlyReference' => 'Un video solo se puede guardar como referencia, no para armar un post.',
    'contentOneVideoAtATime' => 'Subí el video solo, de a uno y sin fotos.',
    'contentVideoType' => 'Ese formato de video no sirve. Probá con uno grabado desde el teléfono (MP4 o MOV).',
    'contentVideoTooBig' => 'El video pesa demasiado. Recortalo o bajale la calidad — el máximo son 50 MB.',
    'contentPhotoTooBig' => 'Alguna foto pesa demasiado. El máximo son 25 MB por foto.',
    'contentBatchTooBig' => 'Todas juntas pesan demasiado. Subí menos fotos a la vez y repetí con el resto.',
    'contentLinkInvalid' => 'Ese enlace no sirve. Pegá la dirección de una imagen que empiece por https.',
    'contentLinkBlocked' => 'Ese enlace apunta a una dirección interna y no se puede traer.',
    'contentLinkUnreachable' => 'No se pudo abrir ese enlace. Comprobá que funcione en el navegador.',
    'contentLinkNotImage' => 'Ese enlace no lleva a una imagen. Abrí la foto sola y copiá esa dirección.',
    'contentLinkTooBig' => 'Esa imagen pesa demasiado.',
    'contentUploadFailed' => 'La subida se cortó antes de terminar. Probá de nuevo, y si es un video, que pese menos de 50 MB.',
    // Se guarda como motivo del bloqueo, así que la ve la profesional en su lista.
    'timeOffPauseReason' => 'Agenda cerrada',
    // Se le manda al cliente por Kapso cuando el WhatsApp de la profesional
    // está conectado — copia idéntica a resources/js/i18n/*.json waMessage*
    // (esa copia solo se MUESTRA, nunca se envía, cuando no hay bot que la mande sola).
    'waMessageConfirmed' => '¡Hola :client! Soy :provider. Tu cita para :service el :date quedó confirmada. ¡Nos vemos!',
    'waMessageRejected' => '¡Hola :client! Soy :provider. No podré atender tu solicitud de :service para el :date. Escríbeme y buscamos otro horario.',
    'waMessageReviewAsk' => '¡Hola :client! Soy :provider. Gracias por venir hoy 💛 ¿Me dejas una reseña? Es un toque: :link',
    'waMessageRescheduled' => '¡Hola :client! Soy :provider. Cambié tu cita de :service: ahora es el :date. Si no te sirve, escríbeme.',
    'waMessageCancelled' => '¡Hola :client! Soy :provider. Tuve que cancelar tu cita de :service del :date. Lamento el inconveniente, escríbeme y la reprogramamos.',
];
