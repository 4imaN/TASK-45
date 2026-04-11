<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:process')->hourly();
Schedule::command('holds:expire')->everyFifteenMinutes();
