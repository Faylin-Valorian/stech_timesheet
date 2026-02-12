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

// --- CORE ---
use OCA\StechTimesheet\Controller\PageController;

// --- ADMIN FEATURES ---
use OCA\StechTimesheet\Features\Admin\Payroll\Controller\PayrollController;
use OCA\StechTimesheet\Features\Admin\Payroll\Service\PayrollService;
use OCA\StechTimesheet\Features\Admin\Payroll\Db\PayrollMapper;

use OCA\StechTimesheet\Features\Admin\Holidays\Controller\HolidayController;
use OCA\StechTimesheet\Features\Admin\Holidays\Service\HolidayService;
use OCA\StechTimesheet\Features\Admin\Holidays\Db\HolidayMapper;

use OCA\StechTimesheet\Features\Admin\Locations\Controller\LocationController;
use OCA\StechTimesheet\Features\Admin\Locations\Service\LocationService;
use OCA\StechTimesheet\Features\Admin\Locations\Db\LocationMapper;

use OCA\StechTimesheet\Features\Admin\Jobs\Controller\JobController as AdminJobController;
use OCA\StechTimesheet\Features\Admin\Jobs\Service\JobService as AdminJobService;
use OCA\StechTimesheet\Features\Admin\Jobs\Db\JobMapper as AdminJobMapper;

use OCA\StechTimesheet\Features\Admin\Users\Controller\UserController;
use OCA\StechTimesheet\Features\Admin\Users\Service\UserService;
use OCA\StechTimesheet\Features\Admin\Users\Db\UserMapper;

// --- ANALYSIS FEATURES ---
use OCA\StechTimesheet\Features\Analysis\Dashboard\Controller\DashboardController;
use OCA\StechTimesheet\Features\Analysis\Dashboard\Service\DashboardService;
use OCA\StechTimesheet\Features\Analysis\Dashboard\Db\DashboardMapper;

use OCA\StechTimesheet\Features\Analysis\Overview\Controller\OverviewController;
use OCA\StechTimesheet\Features\Analysis\Overview\Service\OverviewService;

use OCA\StechTimesheet\Features\Analysis\Travel\Controller\TravelController;
use OCA\StechTimesheet\Features\Analysis\Travel\Service\TravelService;

use OCA\StechTimesheet\Features\Analysis\JobBreakdown\Controller\JobBreakdownController;
use OCA\StechTimesheet\Features\Analysis\JobBreakdown\Service\JobBreakdownService;

use OCA\StechTimesheet\Features\Analysis\JobProfitability\Controller\JobProfitabilityController;
use OCA\StechTimesheet\Features\Analysis\JobProfitability\Service\JobProfitabilityService;

// --- TIMESHEET FEATURES ---
use OCA\StechTimesheet\Features\Timesheet\Calendar\Controller\CalendarController;
use OCA\StechTimesheet\Features\Timesheet\Calendar\Service\CalendarService;
use OCA\StechTimesheet\Features\Timesheet\Calendar\Db\CalendarMapper;

use OCA\StechTimesheet\Features\Timesheet\Entry\Controller\EntryController;
use OCA\StechTimesheet\Features\Timesheet\Entry\Service\EntryService;
use OCA\StechTimesheet\Features\Timesheet\Entry\Db\EntryMapper;


class Application extends App implements IBootstrap {

    public const APP_ID = 'stech_timesheet';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        
        // ============================
        // ADMIN REGISTRATION
        // ============================
        $context->registerService(PayrollMapper::class, function($c) { return new PayrollMapper($c->get(IDBConnection::class)); });
        $context->registerService(PayrollService::class, function($c) { return new PayrollService($c->get(PayrollMapper::class), $c->get(IAppData::class)); });
        $context->registerService(PayrollController::class, function($c) { return new PayrollController($c->get(IRequest::class), $c->get(PayrollService::class)); });

        $context->registerService(HolidayMapper::class, function($c) { return new HolidayMapper($c->get(IDBConnection::class)); });
        $context->registerService(HolidayService::class, function($c) { return new HolidayService($c->get(HolidayMapper::class)); });
        $context->registerService(HolidayController::class, function($c) { return new HolidayController($c->get(IRequest::class), $c->get(HolidayService::class)); });

        $context->registerService(LocationMapper::class, function($c) { return new LocationMapper($c->get(IDBConnection::class)); });
        $context->registerService(LocationService::class, function($c) { return new LocationService($c->get(LocationMapper::class)); });
        $context->registerService(LocationController::class, function($c) { return new LocationController($c->get(IRequest::class), $c->get(LocationService::class)); });

        $context->registerService(AdminJobMapper::class, function($c) { return new AdminJobMapper($c->get(IDBConnection::class)); });
        $context->registerService(AdminJobService::class, function($c) { return new AdminJobService($c->get(AdminJobMapper::class)); });
        $context->registerService(AdminJobController::class, function($c) { return new AdminJobController($c->get(IRequest::class), $c->get(AdminJobService::class)); });

        $context->registerService(UserMapper::class, function($c) { return new UserMapper($c->get(IDBConnection::class)); });
        $context->registerService(UserService::class, function($c) { return new UserService($c->get(UserMapper::class), $c->get(IUserManager::class), $c->get(IGroupManager::class)); });
        $context->registerService(UserController::class, function($c) { return new UserController($c->get(IRequest::class), $c->get(UserService::class)); });


        // ============================
        // TIMESHEET REGISTRATION
        // ============================
        $context->registerService(CalendarMapper::class, function($c) { return new CalendarMapper($c->get(IDBConnection::class)); });
        $context->registerService(CalendarService::class, function($c) { return new CalendarService($c->get(CalendarMapper::class)); });
        $context->registerService(CalendarController::class, function($c) { 
            return new CalendarController(
                $c->get(IRequest::class), 
                $c->get(CalendarService::class), 
                $c->get(IUserSession::class), 
                $c->get(IGroupManager::class)
            ); 
        });

        $context->registerService(EntryMapper::class, function($c) { return new EntryMapper($c->get(IDBConnection::class)); });
        $context->registerService(EntryService::class, function($c) { return new EntryService($c->get(EntryMapper::class)); });
        $context->registerService(EntryController::class, function($c) { 
            return new EntryController(
                $c->get(IRequest::class), 
                $c->get(EntryService::class), 
                $c->get(IUserSession::class), 
                $c->get(IGroupManager::class)
            ); 
        });


        // ============================
        // ANALYSIS REGISTRATION
        // ============================
        // Dashboard (Core)
        $context->registerService(DashboardMapper::class, function($c) { return new DashboardMapper($c->get(IDBConnection::class)); });
        $context->registerService(DashboardService::class, function($c) {
            return new DashboardService(
                $c->get(DashboardMapper::class),
                $c->get(CalendarMapper::class), // <--- This is what caused the error because the Service didn't expect it
                $c->get(IGroupManager::class),
                $c->get(IUserSession::class)
            );
        });
        $context->registerService(DashboardController::class, function($c) {
            return new DashboardController(
                $c->get(IRequest::class),
                $c->get(DashboardService::class),
                $c->get(CalendarMapper::class), // Replacement for TimesheetMapper
                $c->get(IUserSession::class),
                $c->get(IUserManager::class)
            );
        });

        // Overview
        $context->registerService(OverviewService::class, function($c) { return new OverviewService(); });
        $context->registerService(OverviewController::class, function($c) {
            return new OverviewController($c->get(IRequest::class), $c->get(DashboardService::class), $c->get(OverviewService::class));
        });

        // Travel
        $context->registerService(TravelService::class, function($c) { return new TravelService($c->get(CalendarMapper::class)); });
        $context->registerService(TravelController::class, function($c) {
            return new TravelController($c->get(IRequest::class), $c->get(DashboardService::class), $c->get(TravelService::class));
        });

        // Jobs
        $context->registerService(JobBreakdownService::class, function($c) { return new JobBreakdownService(); });
        $context->registerService(JobBreakdownController::class, function($c) {
            return new JobBreakdownController($c->get(IRequest::class), $c->get(DashboardService::class), $c->get(JobBreakdownService::class));
        });

        $context->registerService(JobProfitabilityService::class, function($c) { return new JobProfitabilityService(); });
        $context->registerService(JobProfitabilityController::class, function($c) {
            return new JobProfitabilityController($c->get(IRequest::class), $c->get(DashboardService::class), $c->get(JobProfitabilityService::class));
        });


        // ============================
        // PAGE CONTROLLER
        // ============================
        $context->registerService(PageController::class, function($c) {
            return new PageController(
                $c->get(IRequest::class),
                $c->get(IUserSession::class),
                $c->get(IGroupManager::class),
                $c->get(DashboardService::class) // REPLACES old AnalysisService
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