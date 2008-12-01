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
 * Paginator to list the timeline items.
 */
class IDF_Timeline_Paginator extends Pluf_Paginator
{
    public $current_day = null;

    /**
     * Generate a standard "line" of the body.
     *
     * It is important to note that the table has only 2 columns, so
     * the timelineFragment() method of each item must take that into
     * account.
     */
    function bodyLine($item)
    {
        $doc = Pluf::factory($item->model_class, $item->model_id);
        $doc->public_dtime = $item->public_dtime;
        $item_day = Pluf_Template_dateFormat($item->creation_dtime, 
                                             '%y-%m-%d');
        $out = '';
        if ($this->current_day == null or
            Pluf_Date::dayCompare($this->current_day, $item_day) != 0) {
            $day = Pluf_Template_dateFormat($item->creation_dtime);
            if ($item_day == Pluf_Template_timeFormat(time(), 'y-m-d')) {
                $day = __('Today');
            }
            $out = '<tr><th colspan="2">'.$day.'</th></tr>'."\n";
            $this->current_day = $item_day;
        } 
        return $out.$doc->timelineFragment($item->request);
    }
}
