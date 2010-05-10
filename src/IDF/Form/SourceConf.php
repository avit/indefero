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
 *
 * Only the modification of the login/password for subversion is
 * authorized.
 */
class IDF_Form_SourceConf extends Pluf_Form
{
    public $conf = null;
    public function initFields($extra=array())
    {
        $this->conf = $extra['conf'];
        if ($extra['remote_svn']) {
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
        Pluf::loadFunction('Pluf_HTTP_URL_urlForView');
        $url = Pluf_HTTP_URL_urlForView('idf_faq').'#webhooks';
        $this->fields['webhook_url'] = new Pluf_Form_Field_Url(
                    array('required' => false,
                          'label' => __('Webhook URL'),
                          'initial' => $this->conf->getVal('webhook_url', ''),
                          'help_text' => sprintf(__('Learn more about the <a href="%s">post-commit web hooks</a>.'), $url),
                          'widget_attrs' => array('size' => 35),
                          ));

    }
}


