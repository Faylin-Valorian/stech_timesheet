<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Files\IAppData;

// Controllers
use OCA\StechTimesheet\Controller\PageController;
use OCA\StechTimesheet\Controller\TimesheetController;
use OCA\StechTimesheet\Controller\AdminController;
use OCA\StechTimesheet\Controller\AnalysisController;

// Mappers
use OCA\StechTimesheet\Db\TimesheetMapper;
use OCA\StechTimesheet\Db\AdminMapper;
use OCA\StechTimesheet\Db\AnalysisMapper;

// Services
use OCA\StechTimesheet\Service\TimesheetService;
use OCA\StechTimesheet\Service\AdminService;
use OCA\StechTimesheet\Service\AnalysisService;

class Application extends App implements IBootstrap {

    public function __construct(array $urlParams = []) {
        parent::__construct('stech_timesheet', $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // --- Register Mappers ---
        $context->registerService(TimesheetMapper::class, function($c) {
            return new TimesheetMapper($c->get(IDBConnection::class));
        });

        $context->registerService(AdminMapper::class, function($c) {
            return new AdminMapper($c->get(IDBConnection::class));
        });

        $context->registerService(AnalysisMapper::class, function($c) {
            return new AnalysisMapper($c->get(IDBConnection::class));
        });

        // --- Register Services ---
        $context->registerService(TimesheetService::class, function($c) {
            return new TimesheetService($c->get(TimesheetMapper::class));
        });

        $context->registerService(AdminService::class, function($c) {
            return new AdminService(
                $c->get(IUserManager::class),
                $c->get(AdminMapper::class)
            );
        });

        $context->registerService(AnalysisService::class, function($c) {
            return new AnalysisService($c->get(IDBConnection::class));
        });

        // --- Register Controllers ---
        // FIX: Argument #1 must be IRequest, Argument #2 must be string (AppName)
        // lib/AppInfo/Application.php

        $context->registerService(PageController::class, function($c) {
            return new PageController(
                $c->get(IRequest::class),      // Argument 1
                $c->get(IUserSession::class),  // Argument 2
                $c->get(IDBConnection::class), // Argument 3
                $c->get(IGroupManager::class)  // Argument 4
            );
        });

        $context->registerService(TimesheetController::class, function($c) {
            return new TimesheetController(
                $c->get(IRequest::class),      // Argument 1: $request
                $c->get(IDBConnection::class), // Argument 2: $db
                $c->get(IUserSession::class)   // Argument 3: $userSession
            );
        });

        $context->registerService(AdminController::class, function($c) {
            return new AdminController(
                $c->get(IRequest::class),
                $c->get(IDBConnection::class),
                $c->get(IUserSession::class),
                $c->get(IUserManager::class),
                $c->get(IGroupManager::class),
                $c->get(IAppData::class),
                $c->get(AdminService::class),
                $c->get(AdminMapper::class)
            );
        });

        $context->registerService(AnalysisController::class, function($c) {
            return new AnalysisController(
                $c->get(IRequest::class),
                $c->get(IDBConnection::class),
                $c->get(IUserSession::class),
                $c->get(IGroupManager::class),
                $c->get(AnalysisService::class),
                $c->get(AnalysisMapper::class)
            );
        });
    }

    /**
     * Boot method for Nextcloud 32+
     */
    public function boot(IBootContext $context): void {
        // App-level initialization logic
    }
}