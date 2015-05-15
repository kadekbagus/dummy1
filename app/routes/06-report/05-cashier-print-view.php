<?php

    Route::get('/printer/cashier/list', 'Report\CashierPrinterController@getCashierPrintView');

    // Cashier Time Table Report
    Route::get('/printer/cashier/time-list', 'Report\CashierPrinterController@getCashierTimeReportPrintView');
