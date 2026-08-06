<script setup>
import PublicLayout from '../../Layouts/PublicLayout.vue';
</script>

<template>
    <PublicLayout>
        <article class="p-6 pb-12">
            <h1 class="text-[24px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                Política de privacidad
            </h1>
            <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">Última actualización: agosto de 2026</p>

            <div class="mt-6 flex flex-col gap-5 text-[14px] font-normal leading-relaxed text-[var(--text-body)]">
                <section>
                    <h2 class="mb-1 text-[15px] font-semibold text-[var(--text-strong)]">1. Qué datos guardamos</h2>
                    <p>
                        Para gestionar tu cita guardamos tu <strong>nombre</strong>, tu
                        <strong>número de teléfono</strong> y los datos de la reserva (servicio, fecha y hora).
                        Si eres profesional, además tu correo y la información de tu perfil público.
                    </p>
                </section>
                <section>
                    <h2 class="mb-1 text-[15px] font-semibold text-[var(--text-strong)]">2. Para qué los usamos</h2>
                    <p>
                        Únicamente para gestionar las citas: confirmarlas, recordarlas y que tu profesional pueda
                        contactarte. No vendemos tus datos ni los usamos para publicidad de terceros.
                    </p>
                </section>
                <section>
                    <h2 class="mb-1 text-[15px] font-semibold text-[var(--text-strong)]">3. WhatsApp</h2>
                    <p>
                        Si escribes al WhatsApp de una profesional, la conversación se procesa para poder
                        responderte y agendar tu cita. Escribe <strong>STOP</strong> en cualquier momento para
                        dejar de recibir mensajes, y <strong>START</strong> para reactivarlos.
                    </p>
                </section>
                <section>
                    <h2 class="mb-1 text-[15px] font-semibold text-[var(--text-strong)]">4. Cuánto tiempo</h2>
                    <p>
                        Conservamos los datos de las citas mientras la cuenta de la profesional esté activa, para
                        que tenga su historial. Puedes pedir la eliminación de tus datos en cualquier momento.
                    </p>
                </section>
                <section>
                    <h2 class="mb-1 text-[15px] font-semibold text-[var(--text-strong)]">5. Tus derechos</h2>
                    <p>
                        Puedes pedirnos ver, corregir o eliminar tus datos escribiendo a
                        <a href="mailto:glowstudios@proton.me" class="font-medium text-[var(--green-text)]">glowstudios@proton.me</a>.
                        Respondemos lo antes posible.
                    </p>
                </section>
            </div>
        </article>
    </PublicLayout>
</template>
