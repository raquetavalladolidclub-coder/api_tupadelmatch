<?php

/**
 * Datos de contacto y preguntas frecuentes de "Ayuda y Soporte".
 * Se sirven a la app desde GET /soporte.
 *
 * Edita este archivo para cambiar los datos sin recompilar la app.
 */
return [
    // Datos de contacto que muestra la app.
    'email'            => 'info@tupadelmatch.es',
    'telefono'         => '+34 983 633 005',
    'whatsapp'         => '34647329621',
    'whatsapp_mensaje' => 'Hola, necesito ayuda con la app de Tu Pádel Match',

    // Buzón que recibe los mensajes del formulario de contacto.
    'contacto_destino' => 'info@tupadelmatch.es',

    'faqs' => [
        [
            'pregunta'  => '¿Cómo me uno a un partido?',
            'respuesta' => 'Ve a la pestaña "Partidos", selecciona un partido disponible y pulsa "Inscribirse".',
        ],
        [
            'pregunta'  => '¿Puedo cancelar mi inscripción?',
            'respuesta' => 'Sí. Desde "Mis Partidos" puedes darte de baja hasta 3 horas antes del partido.',
        ],
        [
            'pregunta'  => '¿Cómo funciona el sistema de fiabilidad?',
            'respuesta' => 'Tu fiabilidad sube cuando asistes a los partidos y baja cuando faltas sin avisar. Una fiabilidad alta te da prioridad en partidos populares.',
        ],
        [
            'pregunta'  => '¿Qué categorías existen?',
            'respuesta' => 'Promesas, Cobre, Bronce, Plata, Diamante, Oro y Pro. Se asignan según tu nivel de juego.',
        ],
        [
            'pregunta'  => '¿Cómo contacto con el organizador?',
            'respuesta' => 'Puedes ver la información del organizador en la ficha de cada partido.',
        ],
    ],
];
