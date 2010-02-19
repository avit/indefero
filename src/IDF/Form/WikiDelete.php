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
 * Delete a documentation page.
 *
 * This is a hard delete of the page and the revisions.
 *
 */
class IDF_Form_WikiDelete extends Pluf_Form
{
    protected $page = null;

    public function initFields($extra=array())
    {
        $this->page = $extra['page'];
        $this->fields['confirm'] = new Pluf_Form_Field_Boolean(
                                      array('required' => true,
                                            'label' => __('Yes, I understand that the page and all its revisions will be deleted.'),
                                            'initial' => '',
                                            ));
    }

    /**
     * Check the confirmation.
     */
    public function clean_confirm()
    {
        if (!$this->cleaned_data['confirm']) {
            throw new Pluf_Form_Invalid(__('You need to confirm the deletion.'));
        }
        return $this->cleaned_data['confirm'];
    }


    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }
        $this->page->delete();
        return true;
    }
}
