<?php
namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;

class PageController extends Controller {
    public function __construct($AppName, IRequest $request) {
        parent::__construct($AppName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index() {
        // Load the template 'main' from the 'templates' folder
        return new TemplateResponse('stech_timesheet', 'main');
    }
}