<?php

Class Extension_Singles extends Extension
{
    private $single;

    // delegates

    public function getSubscribedDelegates()
    {
        return array(

            array('page'     => '/backend/',
                  'delegate' => 'AdminPagePostCallback',
                  'callback' => 'adminPagePostCallback'),

            array('page'     => '/backend/',
                  'delegate' => 'AppendPageAlert',
                  'callback' => 'appendPageAlert'),

            array('page'     => '/backend/',
                  'delegate' => 'AdminPagePreGenerate',
                  'callback' => 'adminPagePreGenerate'),

            array('page'     => '/blueprints/sections/',
                  'delegate' => 'AddSectionElements',
                  'callback' => 'addSectionElements')
        );
    }

    // install

    public function install()
    {
        $sql = 'ALTER TABLE `tbl_sections` ' .
               'ADD `single` enum("yes", "no") NOT NULL DEFAULT "no" AFTER `hidden`';

        try {

            return Symphony::Database()->query($sql);

        } catch (DatabaseException $exception) {

            $this->uninstall();

            return false;
        }
    }

    // uninstall

    public function uninstall()
    {
        $sql = 'ALTER TABLE `tbl_sections` ' .
               'DROP `single`';

        try {

            return Symphony::Database()->query($sql);

        } catch (DatabaseException $exception) {

            return false;
        }
    }

    // intercept routing

    public function adminPagePostCallback($context)
    {
        // check driver

        if ($context['callback']['driver'] !== 'publish') {

            return;
        }

        // get context

        $page    = $context['callback']['context']['page'];
        $section = $context['callback']['context']['section_handle'];

        // check section

        if (!$section = SectionManager::fetchIDFromHandle($section)) {

            return;
        }

        if (!$section = SectionManager::fetch($section)) {

            return;
        }

        // check setting

        if ($section->get('single') !== 'yes') {

            return;
        }

        // set flag

        $this->single = true;

        // check page

        if ($page === 'edit') {

            return;
        }

        // check entries

        if ($entries = EntryManager::fetch(null, $section->get('id'), 1, 0)) {

            // set entry

            $context['callback']['context']['entry_id'] = current($entries)->get('id');

            // reroute

            $context['callback']['context']['page'] = 'edit';

        } else {

            // reroute

            $context['callback']['context']['page'] = 'new';
        }
    }

    // modify page alert

    public function appendPageAlert()
    {
        // check flag

        if (!$this->single) {

            return;
        }

        // check alerts

        if (!is_array($alerts = Administration::instance()->Page->Alert)) {

            return;
        }

        // modify success alert

        foreach ($alerts as $alert) {

            if ($alert->type !== Alert::SUCCESS) {

                continue;
            }

            $alert->message = substr($alert->message, 0, strpos($alert->message, '.'));
        }
    }

    // modify breadcrumbs

    public function adminPagePreGenerate($context)
    {
        // check flag

        if (!$this->single) {

            return;
        }

        // get breadcrumbs

        $breadcrumbs = $context['oPage']->Breadcrumbs;

        // get section name

        $section = $breadcrumbs
            ->getChild(0)
            ->getChild(0)
            ->getChild(0)
            ->getValue();

        // set subheading

        $breadcrumbs->getChild(1)->replaceValue($section);

        // remove breadcrumbs

        $breadcrumbs->removeChildAt(0);
    }

    // preferences

    public function addSectionElements($context)
    {
        // create custom setting

        $label    = Widget::Label(__('Single entry section'));
        $hidden   = Widget::Input('meta[single]', 'no',  'hidden');
        $checkbox = Widget::Input('meta[single]', 'yes', 'checkbox');

        if ($context['meta']['single'] === 'yes') {

            $checkbox->setAttribute('checked', '');
        }

        $label->prependChild($checkbox);
        $label->prependChild($hidden);

        // append custom setting

        $context['form']
            ->getChildByName('fieldset', 1)
            ->getChildByName('div', 0)
            ->getChildByName('div', 0)
            ->appendChild($label);
    }
}
