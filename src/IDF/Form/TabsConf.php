<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */


/**
 * Configuration of the tabs access.
 */
class IDF_Form_TabsConf extends Pluf_Form
{
    public $conf = null;
    public $project = null;

    public function initFields($extra=array())
    {
        $this->conf = $extra['conf'];
        $this->project = $extra['project'];

        $ak = array('downloads_access_rights' => __('Downloads'),
                    'review_access_rights' => __('Code Review'),
                    'wiki_access_rights' => __('Documentation'),
                    'source_access_rights' => __('Source'),
                    'issues_access_rights' => __('Issues'),);
        foreach ($ak as $key=>$label) {
            $this->fields[$key] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => $label,
                                            'initial' => $this->conf->getVal($key, 'all'),
                                            'widget_attrs' => array('choices' => 
                                          array(
                                                __('Open to all') => 'all',
                                                __('Signed in users') => 'login',
                                                __('Project members') => 'members',
                                                __('Project owners') => 'owners',
                                                __('Closed') => 'none',
                                                )
                                                                    ),
                                            'widget' => 'Pluf_Form_Widget_SelectInput',
                                            ));
        }
        $ak = array('downloads_notification_email',
                    'review_notification_email',
                    'wiki_notification_email',
                    'source_notification_email',
                    'issues_notification_email',);
        foreach ($ak as $key) {
            $this->fields[$key] = new Pluf_Form_Field_Email(
                                      array('required' => false,
                                            'label' => $key,
                                            'initial' => $this->conf->getVal($key, ''),
                                            ));
        }


        $this->fields['private_project'] = new Pluf_Form_Field_Boolean(
                    array('required' => false,
                          'label' => __('Private project'),
                          'initial' => $this->project->private,
                          'widget' => 'Pluf_Form_Widget_CheckboxInput',
                          ));
        $this->fields['authorized_users'] = new Pluf_Form_Field_Varchar(
                          array('required' => false,
                                'label' => __('Extra authorized users'),
                                'widget_attrs' => array('rows' => 7,
                                                        'cols' => 40),
                                'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            ));
    }

    public function clean_authorized_users()
    {
        return IDF_Form_MembersConf::checkBadLogins($this->cleaned_data['authorized_users']);
    }

    public function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }
        // remove all the permissions
        $perm = Pluf_Permission::getFromString('IDF.project-authorized-user');
        $cm = $this->project->getMembershipData();
        $guser = new Pluf_User();
        foreach ($cm['authorized'] as $user) {
                Pluf_RowPermission::remove($user, $this->project, $perm);
        }
        if ($this->cleaned_data['private_project']) {
            foreach (preg_split("/\015\012|\015|\012|\,/", $this->cleaned_data['authorized_users'], -1, PREG_SPLIT_NO_EMPTY) as $login) {
                $sql = new Pluf_SQL('login=%s', array(trim($login)));
                $users = $guser->getList(array('filter'=>$sql->gen()));
                if ($users->count() == 1) {
                    Pluf_RowPermission::add($users[0], $this->project, $perm);
                }
            }
            $this->project->private = 1;
        } else {
            $this->project->private = 0;
        }
        $this->project->update();
        $this->project->membershipsUpdated();
    }
}



