<?php

namespace Icinga\Module\Director\Dashboard;

class DataDashboard extends Dashboard
{
    protected $name;

    protected $dashletNames = array(
        'ImportSource',
        'Sync',
        'Job',
        'Datafield',
        'Datalist',
    );

    public function getTitle()
    {
        return $this->translate('Do more with your data');
    }

    public function getDescription()
    {
        return $this->translate('...');
    }
}
