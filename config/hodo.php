<?php

return [
    /*
    | Règles de pointage. Une arrivée après (heure de début + tolérance) est
    | enregistrée avec le statut « late » (retard).
    */
    'work_start_time' => env('WORK_START_TIME', '08:00'),
    'late_grace_minutes' => (int) env('LATE_GRACE_MINUTES', 15),

    // Jours non travaillés (1 = lundi ... 7 = dimanche) : pas d'absence automatique.
    'non_working_days' => array_map('intval', array_filter(explode(',', (string) env('NON_WORKING_DAYS', '6,7')))),

    // Types de pièces acceptés dans le dossier d'un employé.
    'document_types' => ['contrat', 'piece_identite', 'diplome', 'attestation', 'autre'],

    // Types que l'employé peut déposer lui-même (le contrat reste réservé à l'administration).
    'employee_document_types' => ['piece_identite', 'diplome', 'attestation', 'autre'],

    // Nombre maximum de pièces dans un dossier.
    'max_documents_per_employee' => 30,
];
