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
 * Create a project.
 *
 * A kind of merge of the member configuration, overview and the
 * former source tab.
 *
 */
class IDF_Form_Admin_ProjectCreate extends Pluf_Form
{
    public function initFields($extra=array())
    {
        $choices = array();
        $options = array(
                         'git' => __('git'),
                         'svn' => __('Subversion'),
                         'mercurial' => __('mercurial'),
                         );
        foreach (Pluf::f('allowed_scm', array()) as $key => $class) {
            $choices[$options[$key]] = $key;
        }

        $this->fields['name'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Name'),
                                            'initial' => '',
                                            ));

        $this->fields['private_project'] = new Pluf_Form_Field_Boolean(
                    array('required' => false,
                          'label' => __('Private project'),
                          'initial' => false,
                          'widget' => 'Pluf_Form_Widget_CheckboxInput',
                          ));

        $this->fields['shortname'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Shortname'),
                                            'initial' => '',
                                            'help_text' => __('It must be unique for each project and composed only of letters, digits and dash (-) like "my-project".'),
                                            ));

        $this->fields['scm'] = new Pluf_Form_Field_Varchar(
                    array('required' => true,
                          'label' => __('Repository type'),
                          'initial' => 'git',
                          'widget_attrs' => array('choices' => $choices),
                          'widget' => 'Pluf_Form_Widget_SelectInput',
                          ));

        $this->fields['svn_remote_url'] = new Pluf_Form_Field_Varchar(
                    array('required' => false,
                          'label' => __('Remote Subversion repository'),
                          'initial' => '',
                          'widget_attrs' => array('size' => '30'),
                          ));

        $this->fields['svn_username'] = new Pluf_Form_Field_Varchar(
                    array('required' => false,
                          'label' => __('Repository username'),
                          'initial' => '',
                          'widget_attrs' => array('size' => '15'),
                          ));

        $this->fields['svn_password'] = new Pluf_Form_Field_Varchar(
                    array('required' => false,
                          'label' => __('Repository password'),
                          'initial' => '',
                          'widget' => 'Pluf_Form_Widget_PasswordInput',
                          ));

        $this->fields['owners'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Project owners'),
                                            'initial' => $extra['user']->login,
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array('rows' => 5,
                                                                    'cols' => 40),
                                            ));

        $this->fields['members'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Project members'),
                                            'initial' => '',
                                            'widget_attrs' => array('rows' => 7,
                                                                    'cols' => 40),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            ));
        /**
         * [signal]
         *
         * IDF_Form_Admin_ProjectCreate::initFields
         *
         * [sender]
         *
         * IDF_Form_Admin_ProjectCreate
         *
         * [description]
         *
         * This signal allows an application to modify the form
         * for the creation of a project.
         *
         * [parameters]
         *
         * array('form' => $form)
         *
         */
        $params = array('form' => $this);
        Pluf_Signal::send('IDF_Form_Admin_ProjectCreate::initFields',
                          'IDF_Form_Admin_ProjectCreate', $params);
    }

    public function clean_owners()
    {
        return IDF_Form_MembersConf::checkBadLogins($this->cleaned_data['owners']);
    }

    public function clean_members()
    {
        return IDF_Form_MembersConf::checkBadLogins($this->cleaned_data['members']);
    }

    public function clean_svn_remote_url()
    {
        $this->cleaned_data['svn_remote_url'] = (!empty($this->cleaned_data['svn_remote_url'])) ? $this->cleaned_data['svn_remote_url'] : '';
        $url = trim($this->cleaned_data['svn_remote_url']);
        if (strlen($url) == 0) return $url;
        // we accept only starting with http(s):// to avoid people
        // trying to access the local filesystem.
        if (!preg_match('#^(http|https)://#', $url)) {
            throw new Pluf_Form_Invalid(__('Only a remote repository available throught http or https are allowed. For example "http://somewhere.com/svn/trunk".'));
        }
        return $url;
    }

    public function clean_shortname()
    {
        $shortname = mb_strtolower($this->cleaned_data['shortname']);
        if (preg_match('/[^\-A-Za-z0-9]/', $shortname)) {
            throw new Pluf_Form_Invalid(__('This shortname contains illegal characters, please use only letters, digits and dash (-).'));
        }
        if (mb_substr($shortname, 0, 1) == '-') {
            throw new Pluf_Form_Invalid(__('The shortname cannot start with the dash (-) character.'));
        }
        if (mb_substr($shortname, -1) == '-') {
            throw new Pluf_Form_Invalid(__('The shortname cannot end with the dash (-) character.'));
        }
        $sql = new Pluf_SQL('shortname=%s', array($shortname));
        $l = Pluf::factory('IDF_Project')->getList(array('filter'=>$sql->gen()));
        if ($l->count() > 0) {
            throw new Pluf_Form_Invalid(__('This shortname is already used. Please select another one.'));
        }
        return $shortname;
    }

    public function clean()
    {
        if ($this->cleaned_data['scm'] != 'svn') {
            foreach (array('svn_remote_url', 'svn_username', 'svn_password')
                     as $key) {
                $this->cleaned_data[$key] = '';
            }
        }
        /**
         * [signal]
         *
         * IDF_Form_Admin_ProjectCreate::clean
         *
         * [sender]
         *
         * IDF_Form_Admin_ProjectCreate
         *
         * [description]
         *
         * This signal allows an application to clean the form
         * for the creation of a project.
         *
         * [parameters]
         *
         * array('cleaned_data' => $cleaned_data)
         *
         */
        $params = array('cleaned_data' => $this->cleaned_data);
        Pluf_Signal::send('IDF_Form_Admin_ProjectCreate::clean',
                          'IDF_Form_Admin_ProjectCreate', $params);
        return $this->cleaned_data;
    }

    public function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }
        $project = new IDF_Project();
        $project->name = $this->cleaned_data['name'];
        $project->shortname = $this->cleaned_data['shortname'];
        $project->private = $this->cleaned_data['private_project'];
        $project->description = __('Click on the Project Management tab to set the description of your project.');
        $project->create();
        $conf = new IDF_Conf();
        $conf->setProject($project);
        $keys = array('scm', 'svn_remote_url', 
                      'svn_username', 'svn_password');
        foreach ($keys as $key) {
            $this->cleaned_data[$key] = (!empty($this->cleaned_data[$key])) ? 
                $this->cleaned_data[$key] : '';
            $conf->setVal($key, $this->cleaned_data[$key]);
        }
        $project->created();
        IDF_Form_MembersConf::updateMemberships($project, 
                                                $this->cleaned_data);
        $project->membershipsUpdated();
        return $project;
    }
}


