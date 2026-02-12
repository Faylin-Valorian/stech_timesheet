<?php
/** @var array $_ */
use OCP\Util;

Util::addScript('stech_timesheet', 'admin-main');
?>

<div id="app">
    <div id="app-navigation">
        <ul class="with-icon">
             <li class="nav-item">
                <a class="nav-link" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.page.index')); ?>">
                    <span class="icon-history"></span><span>Back to Timesheet</span>
                </a>
            </li>
        </ul>
    </div>

    <div id="app-content">
        <div class="admin-container">
            <h1>Administration</h1>
            
            <div id="admin-feature-container">
                <?php 
                // We define the list of enabled feature fragments here.
                // In the future, this list can come from the Controller.
                // Define the modular order
                $features = ['payroll', 'holidays', 'locations', 'jobs']; 
                
                foreach ($features as $feature) {
                    // This includes templates/admin/features/{feature}.php
                    print_unescaped($this->inc('admin/features/' . $feature));
                }
                ?>
            </div>
        </div>
    </div>
</div>