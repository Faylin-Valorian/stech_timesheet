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
use OCP\INavigationManager;

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
use OCA\StechTimesheet\Service\HolidayService;

class Application extends App implements IBootstrap {

    public const APP_ID = 'stech_timesheet';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
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
            return new TimesheetService(
                $c->get(TimesheetMapper::class),
                $c->get(IDBConnection::class)
            );
        });

        $context->registerService(AdminService::class, function($c) {
            return new AdminService(
                $c->get(IUserManager::class),
                $c->get(AdminMapper::class),
                $c->get(IAppData::class)
            );
        });

        $context->registerService(AnalysisService::class, function($c) {
            return new AnalysisService(
                $c->get(AnalysisMapper::class),
                $c->get(TimesheetMapper::class),
                $c->get(IGroupManager::class),
                $c->get(IUserSession::class)
            );
        });

        $context->registerService(HolidayService::class, function($c) {
            return new HolidayService(
                $c->get(IDBConnection::class),
                $c->get(AdminMapper::class)
            );
        });

        // --- Register Controllers ---
        
        // PATCH: Updated for new PageController signature
        // Removed IDBConnection, Added AnalysisService
        $context->registerService(PageController::class, function($c) {
            return new PageController(
                $c->get(IRequest::class),
                $c->get(IUserSession::class),
                $c->get(IGroupManager::class),
                $c->get(AnalysisService::class)
            );
        });

        $context->registerService(TimesheetController::class, function ($c) {
            return new TimesheetController(
                $c->get(IRequest::class),
                $c->get(IUserSession::class),
                $c->get(TimesheetService::class),
                $c->get(TimesheetMapper::class),
                $c->get(IDBConnection::class),
                $c->get(IGroupManager::class) 
            );
        });

        // PATCH: Updated for new AdminController signature
        // Added AnalysisService at the end
        $context->registerService(AdminController::class, function($c) {
            return new AdminController(
                $c->get(IRequest::class),
                $c->get(IDBConnection::class),
                $c->get(AdminService::class),
                $c->get(AdminMapper::class),
                $c->get(IGroupManager::class),
                $c->get(IAppData::class),
                $c->get(AnalysisService::class)
            );
        });

        $context->registerService(AnalysisController::class, function($c) {
            return new AnalysisController(
                $c->get(IRequest::class),
                $c->get(AnalysisService::class),
                $c->get(TimesheetMapper::class),
                $c->get(AnalysisMapper::class),
                $c->get(IUserSession::class),
                $c->get(IUserManager::class)
            );
        });
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function(INavigationManager $navigationManager) {
            $navigationManager->add(function() {
                return [
                    'id' => self::APP_ID,
                    'order' => 10,
                    'href' => \OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.page.index'),
                    'icon' => \OC::$server->getURLGenerator()->imagePath(self::APP_ID, 'app.svg'),
                    'name' => 'Timesheet',
                ];
            });
        });
    }
}