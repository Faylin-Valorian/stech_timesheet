<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCA\StechTimesheet\AppInfo\Application;

class PageController extends Controller {
    public function __construct(IRequest $request) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        $response = new TemplateResponse('stech_timesheet', 'main');

        // Create the CSP object
        $csp = new ContentSecurityPolicy();

        // correct method names for Nextcloud v32+
        $csp->addAllowedScriptDomain('cdn.jsdelivr.net');
        $csp->addAllowedStyleDomain('cdn.jsdelivr.net');

        // Apply the policy
        $response->setContentSecurityPolicy($csp);

        return $response;
    }
}