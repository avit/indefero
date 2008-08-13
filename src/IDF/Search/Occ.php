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
 * Storage of the occurence of the words.
 */
class IDF_Search_Occ extends Pluf_Model
{
    public $_model = 'IDF_Search_Occ';

    function init()
    {
        $this->_a['verbose'] = __('occurence');
        $this->_a['table'] = 'idf_search_occs';
        $this->_a['model'] = 'IDF_Search_Occ';
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  //It is automatically added.
                                  'blank' => true, 
                                  ),
                            'word' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_Search_Word',
                                  'blank' => false,
                                  'verbose' => __('word'),
                                   ),
                            'model_class' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 150,
                                  'verbose' => __('model class'),
                                  ),
                            'model_id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  'verbose' => __('model id'),
                                  ),
                            'project' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_Project',
                                  'blank' => false,
                                  'verbose' => __('project'),
                                   ),
                            'occ' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  'verbose' => __('occurences'),
                                  ),
                            'pondocc' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Float',
                                  'blank' => false,
                                  'verbose' => __('ponderated occurence'),
                                  ),
                            );
        $this->_a['idx'] = array(                           
                            'model_class_id_combo_word_idx' =>
                            array(
                                  'type' => 'unique',
                                  'col' => 'model_class, model_id, word',
                                  ),
                            );

    }

    function __toString()
    {
        return $this->word;
    }
}

