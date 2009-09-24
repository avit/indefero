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
 * Change the email address of a user.
 *
 */
class IDF_Form_UserChangeEmail extends Pluf_Form
{
    protected $user;

    public function initFields($extra=array())
    {
        $this->fields['key'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Your verification key'),
                                            'initial' => '',
                                            'widget_attrs' => array(
                                                       'size' => 50,
                                                                    ),
                                            ));
    }

    function clean_key()
    {
        self::validateKey($this->cleaned_data['key']);
        return $this->cleaned_data['key'];
    }

    /**
     * Validate the key.
     *
     * Throw a Pluf_Form_Invalid exception if the key is not valid.
     *
     * @param string Key
     * @return array array($new_email, $user_id, time())
     */
    public static function validateKey($key)
    {
        $hash = substr($key, 0, 2);
        $encrypted = substr($key, 2);
        if ($hash != substr(md5(Pluf::f('secret_key').$encrypted), 0, 2)) {
            throw new Pluf_Form_Invalid(__('The validation key is not valid. Please copy/paste it from your confirmation email.'));
        }
        $cr = new Pluf_Crypt(md5(Pluf::f('secret_key')));
        return explode(':', $cr->decrypt($encrypted), 3);
        
    }

    /**
     * Save the model in the database.
     *
     * @param bool Commit in the database or not. If not, the object
     *             is returned but not saved in the database.
     * @return Object Model with data set from the form.
     */
    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }
        return Pluf::f('url_base').Pluf_HTTP_URL_urlForView('IDF_Views_User::changeEmailDo', array($this->cleaned_data['key']));
    }
}
