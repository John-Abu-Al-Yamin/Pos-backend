<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaintenanceTicket\StoreMaintenanceTicketRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Maintenance\MaintenanceTicketService;

class MaintenanceTicketController extends Controller
{
    public function __construct(
        private MaintenanceTicketService $maintenanceTicketService
    ) {}

    public function store(StoreMaintenanceTicketRequest $request)
    {
        $ticket = $this->maintenanceTicketService->createTicket(
            $request->validated()
        );

        return ApiResponse::success(
            message: 'تم إنشاء تذكرة الصيانة بنجاح',
            data: $ticket,
            statusCode: 201
        );
    }
}
