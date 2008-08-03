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
 * Configuration of the labels etc. for the uploaded files.
 */
class IDF_Form_UploadConf extends Pluf_Form
{
    /**
     * Defined as constants to easily access the value in the
     * form in the case nothing is in the db yet.
     */
    const init_predefined = 'Featured             = Listed on project home page
Type:Executable      = Executable application
Type:Installer       = Download and run to install application
Type:Package         = Your OS package manager installs this
Type:Archive         = Download, unarchive, then follow directions
Type:Source          = Source code archive
Type:Docs            = This file contains documentation
OpSys:All            = Works with all operating systems
OpSys:Windows        = Works with Windows
OpSys:Linux          = Works with Linux
OpSys:OSX            = Works with Mac OS X
Deprecated           = Most users should NOT download this';

    const init_one_max = 'Type';

    public function initFields($extra=array())
    {
        $this->fields['labels_download_predefined'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Predefined download labels'),
                                            'initial' => self::init_predefined,
                                            'widget_attrs' => array('rows' => 13,
                                                                    'cols' => 75),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            ));

        $this->fields['labels_download_one_max'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Each download may have at most one label with each of these classes'),
                                            'initial' => self::init_one_max, 
                                            'widget_attrs' => array('size' => 60),
                                            ));

    }
}


