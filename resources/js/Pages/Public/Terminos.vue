<script setup>
import PublicLayout from '../../Layouts/PublicLayout.vue';
</script>

<template>
    <PublicLayout>
        <article class="p-6 pb-12">
            <h1 class="text-[24px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                Condiciones de uso
            </h1>
            <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">Última actualización: agosto de 2026</p>

            <div class="mt-6 flex flex-col gap-5 text-[14px] font-normal leading-relaxed text-[var(--text-body)]">
                <section>
                    <h2 class="mb-1 text-[15px] font-semibold text-[var(--text-strong)]">1. Qué es Glowstudio VIP</h2>
                    <p>
                        Glowstudio VIP es una plataforma de reservas que conecta a profesionales de la belleza
                        (estilistas, barberos, manicuristas) con sus clientas. Cada profesional gestiona su propia
                        agenda, sus servicios y sus precios.
                    </p>
                </section>
                <section>
                    <h2 class="mb-1 text-[15px] font-semibold text-[var(--text-strong)]">2. Las reservas</h2>
                    <p>
                        Al reservar, tu solicitud queda <strong>pendiente</strong> hasta que la profesional la
                        confirme. Los precios mostrados los define cada profesional y el pago se realiza
                        directamente con ella, en su establecimiento, salvo que se indique lo contrario.
                    </p>
                </section>
                <section>
                    <h2 class="mb-1 text-[15px] font-semibold text-[var(--text-strong)]">3. Cancelaciones</h2>
                    <p>
                        Cada profesional define su propia política de cancelación. Si necesitas cambiar o cancelar
                        una cita, contáctala directamente — por WhatsApp o por teléfono — con la mayor antelación
                        posible.
                    </p>
                </section>
                <section>
                    <h2 class="mb-1 text-[15px] font-semibold text-[var(--text-strong)]">4. El asistente de WhatsApp</h2>
                    <p>
                        Algunos números de la plataforma son atendidos por un asistente automático que ayuda a
                        agendar citas. Puedes pedir hablar con una persona en cualquier momento, o escribir
                        <strong>STOP</strong> para dejar de recibir mensajes.
                    </p>
                </section>
                <section>
                    <h2 class="mb-1 text-[15px] font-semibold text-[var(--text-strong)]">5. Uso correcto</h2>
                    <p>
                        No está permitido usar la plataforma para hacer reservas falsas, suplantar a otras
                        personas o cualquier actividad contraria a la ley. Las cuentas que lo hagan pueden ser
                        eliminadas.
                    </p>
                </section>
                <section>
                    <h2 class="mb-1 text-[15px] font-semibold text-[var(--text-strong)]">6. Contacto</h2>
                    <p>
                        Para cualquier duda sobre estas condiciones escríbenos a
                        <a href="mailto:glowstudios@proton.me" class="font-medium text-[var(--green-text)]">glowstudios@proton.me</a>.
                    </p>
                </section>
            </div>
        </article>
    </PublicLayout>
</template>
