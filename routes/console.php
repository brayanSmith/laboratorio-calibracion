<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('recordatorios:enviar')->everyMinute()->withoutOverlapping();
