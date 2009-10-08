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
 * Add comments to files in a review.
 *
 */
class IDF_Form_ReviewFileComment extends Pluf_Form
{
    public $files = null;
    public $patch = null;
    public $user = null;

    public function initFields($extra=array())
    {
        $this->files = $extra['files'];
        $this->patch = $extra['patch'];
        $this->user = $extra['user'];
        foreach ($this->files as $filename => $def) {
            $this->fields[md5($filename)] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Comment'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array(
                                                       'cols' => 58,
                                                       'rows' => 9,
                                                                    ),
                                            ));
        }
    }


    /**
     * Validate the interconnection in the form.
     */
    public function clean()
    {
        foreach ($this->files as $filename => $def) {
            if (!empty($this->cleaned_data[md5($filename)])) {
                return $this->cleaned_data;
            }
        }
        throw new Pluf_Form_Invalid(__('You need to provide comments on at least one file.'));
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
        // create a base comment
        $bc = new IDF_Review_Comment();
        $bc->patch = $this->patch;
        $bc->submitter = $this->user;
        $bc->create();
        foreach ($this->files as $filename => $def) {
            if (!empty($this->cleaned_data[md5($filename)])) {
                // Add a comment.
                $c = new IDF_Review_FileComment();
                $c->comment = $bc;
                $c->cfile = $filename;
                $c->content = $this->cleaned_data[md5($filename)];
                $c->create();
            }
        }
        $this->patch->get_review()->update(); // reindex and put up in
                                              // the list.
        return $bc;
    }
}
