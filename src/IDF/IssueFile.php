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
 * A file uploaded with an issue or a comment to an issue.
 *
 */
class IDF_IssueFile extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_issuefiles';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  //It is automatically added.
                                  'blank' => true, 
                                  ),
                            'comment' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_IssueComment',
                                  'blank' => false,
                                  'verbose' => __('comment'),
                                  'relate_name' => 'attachment',
                                   ),
                            'submitter' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('submitter'),
                                   ),
                            'filename' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => true,
                                  'size' => 100,
                                  'verbose' => __('file name'),
                                  ),
                            'attachment' => 
                            array(
                                  'type' => 'Pluf_DB_Field_File',
                                  'blank' => false,
                                  'verbose' => __('the file'),
                                  ),
                            'filesize' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => true,
                                  'verbose' => __('file size'),
                                  'help_text' => 'Size in bytes.',
                                  ),
                            'type' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 10,
                                  'verbose' => __('type'),
                                  'choices' => array(
                                                     __('Image') => 'img',
                                                     __('Other') => 'other',
                                                     ),
                                  'default' => 'other',
                                  'help_text' => 'The type is to display a thumbnail of the image.',
                                  ),
                            'creation_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'verbose' => __('creation date'),
                                  ),
                            'modif_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'verbose' => __('modification date'),
                                  ),
                            );
    }

    function preSave($create=false)
    {
        if ($this->id == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
            $file = Pluf::f('upload_issue_path').'/'.$this->attachment;
            $this->filesize = filesize($file);
            // remove .dummy
            $this->filename = substr(basename($file), 0, -6); 
            $img_extensions = array('jpeg', 'jpg', 'png', 'gif');
            $info = pathinfo($this->filename);
            if (!isset($info['extension'])) $info['extension'] = '';
            if (in_array(strtolower($info['extension']), $img_extensions)) {
                $this->type = 'img';
            } else {
                $this->type = 'other';
            }
        }
        $this->modif_dtime = gmdate('Y-m-d H:i:s');
    }

    function preDelete()
    {
        @unlink(Pluf::f('upload_issue_path').'/'.$this->attachment);
    }
}
