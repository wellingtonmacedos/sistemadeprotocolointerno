<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup automático: o comando decide, por Câmara, se o backup está no horário configurado.
Schedule::command('backup:run')->hourly()->withoutOverlapping();

// Processamento da fila em hospedagem compartilhada (cPanel), sem Supervisor.
// Em VPS com Supervisor, este agendamento é inofensivo (a fila fica vazia).
Schedule::command('queue:work --stop-when-empty --max-time=280')->everyMinute()->withoutOverlapping();
