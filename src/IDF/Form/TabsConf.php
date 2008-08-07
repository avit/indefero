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
    public function initFields($extra=array())
    {
        $this->conf = $extra['conf'];
        $ak = array('downloads_access_rights' => __('Downloads'),
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
    }
}


