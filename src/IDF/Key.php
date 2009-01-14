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
 * Storage of the SSH keys.
 *
 */
class IDF_Key extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_keys';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  //It is automatically added.
                                  'blank' => true, 
                                  ),
                            'user' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('user'),
                                  ),
                            'content' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Text',
                                  'blank' => false,
                                  'verbose' => __('ssh key'),
                                  ),
                            );
        // WARNING: Not using getSqlTable on the Pluf_User object to
        // avoid recursion.
        $t_users = $this->_con->pfx.'users'; 
        $this->_a['views'] = array(
                              'join_user' => 
                              array(
                                    'join' => 'LEFT JOIN '.$t_users
                                    .' ON '.$t_users.'.id='.$this->_con->qn('user'),
                                    'select' => $this->getSelect().', '
                                    .$t_users.'.login AS login',
                                    'props' => array('login' => 'login'),
                                    )
                                   );
    }

    function postSave($create=false)
    {
        /**
         * [signal]
         *
         * IDF_Key::postSave
         *
         * [sender]
         *
         * IDF_Key
         *
         * [description]
         *
         * This signal allows an application to perform special
         * operations after the saving of a SSH Key.
         *
         * [parameters]
         *
         * array('key' => $key,
         *       'created' => true/false)
         *
         */
        $params = array('key' => $this, 'created' => $create);
        Pluf_Signal::send('IDF_Key::postSave',
                          'IDF_Key', $params);
    }

}
