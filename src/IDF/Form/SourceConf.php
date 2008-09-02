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
 * Configuration of the source.
 */
class IDF_Form_SourceConf extends Pluf_Form
{
    public $conf = null;
    public function initFields($extra=array())
    {
        $this->conf = $extra['conf'];
        $this->fields['scm'] = new Pluf_Form_Field_Varchar(
                    array('required' => true,
                          'label' => __('Repository type'),
                          'initial' => $this->conf->getVal('scm', 'git'),
                          'widget_attrs' => array('choices' => 
                                  array(
                                        __('git') => 'git',
                                        __('Subversion') => 'svn',
                                        )
                                                  ),
                          'widget' => 'Pluf_Form_Widget_SelectInput',
                          ));
        $this->fields['svn_remote_url'] = new Pluf_Form_Field_Varchar(
                    array('required' => false,
                          'label' => __('Remote Subversion repository'),
                          'initial' => $this->conf->getVal('svn_remote_url', ''),
                          'widget_attrs' => array('size' => '30'),
                          ));

        $this->fields['svn_username'] = new Pluf_Form_Field_Varchar(
                    array('required' => false,
                          'label' => __('Repository username'),
                          'initial' => $this->conf->getVal('svn_username', ''),
                          'widget_attrs' => array('size' => '15'),
                          ));

        $this->fields['svn_password'] = new Pluf_Form_Field_Varchar(
                    array('required' => false,
                          'label' => __('Repository password'),
                          'initial' => $this->conf->getVal('svn_password', ''),
                          'widget' => 'Pluf_Form_Widget_PasswordInput',
                          ));
    }

    public function clean_svn_remote_url()
    {
        $url = trim($this->cleaned_data['svn_remote_url']);
        if (strlen($url) == 0) return $url;
        // we accept only starting with http(s):// to avoid people
        // trying to access the local filesystem.
        if (!preg_match('#^(http|https)://#', $url)) {
            throw new Pluf_Form_Invalid(__('Only a remote repository available throught http or https are allowed. For example "http://somewhere.com/sv/trunk.'));
        }
        return $url;
    }

    public function clean()
    {
        if ($this->cleaned_data['scm'] == 'git') {
            foreach (array('svn_remote_url', 'svn_username', 'svn_password')
                     as $key) {
                $this->cleaned_data[$key] = '';
            }
        }
        return $this->cleaned_data;
    }
}


