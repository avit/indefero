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
   * Should be renamed MarkdownPostfilter.
   */
class IDF_Template_MarkdownPrefilter extends Pluf_Text_HTML_Filter
{
    public $allowed_entities = array(
                                     'amp',
                                     'gt',
                                     'lt',
                                     'quot',
                                     'nbsp',
                                     'ndash',
                                     'rdquo',
                                     'ldquo',
                                     'Alpha',
                                     'Beta', 
                                     'Gamma', 
                                     'Delta', 
                                     'Epsilon', 
                                     'Zeta', 
                                     'Eta', 
                                     'Theta', 
                                     'Iota', 
                                     'Kappa', 
                                     'Lambda', 
                                     'Mu', 
                                     'Nu', 
                                     'Xi', 
                                     'Omicron', 
                                     'Pi', 
                                     'Rho', 
                                     'Sigma', 
                                     'Tau', 
                                     'Upsilon', 
                                     'Phi', 
                                     'Chi', 
                                     'Psi', 
                                     'Omega', 
                                     'alpha', 
                                     'beta', 
                                     'gamma', 
                                     'delta', 
                                     'epsilon', 
                                     'zeta', 
                                     'eta', 
                                     'theta', 
                                     'iota', 
                                     'kappa', 
                                     'lambda', 
                                     'mu', 
                                     'nu', 
                                     'xi', 
                                     'omicron', 
                                     'pi', 
                                     'rho', 
                                     'sigmaf', 
                                     'sigma', 
                                     'tau', 
                                     'upsilon', 
                                     'phi', 
                                     'chi', 
                                     'psi', 
                                     'omega', 
                                     'thetasym', 
                                     'upsih', 
                                     'piv',
                                     );

    public $allowed = array(
                            'a' => array('href', 'title', 'rel'),
                            'abbr' => array('title'),
                            'address' => array(),
                            'b' => array(),
                            'blockquote' => array(),
                            'br' => array(),
                            'caption' => array(),
                            'code' => array(),
                            'dd' => array(),
                            'div' => array('align', 'class'),
                            'dl' => array(),
                            'dt' => array(),
                            'em' => array(),
                            'h1' => array('id'),
                            'h2' => array('id'),
                            'h3' => array('id'),
                            'h4' => array('id'),
                            'h5' => array('id'),
                            'h6' => array('id'),
                            'hr' => array(),
                            'i' => array(),
                            'img' => array('src', 'class', 'alt', 'height', 'width', 'style'),
                            'li' => array(),
                            'ol' => array(),
                            'p' => array('align', 'class'),
                            'pre' => array(),
                            'strong' => array(),
                            'table' => array('summary'),
                            'td' => array('style'),
                            'tr' => array(),
                            'ul' => array(),
                            );
    // tags which should always be self-closing (e.g. "<img />")
    public $no_close = array(
                             'img',
                             'br',
                             'hr',
                             );

    // tags which must always have seperate opening and closing tags
    // (e.g. "<b></b>")
    public $always_close = array(
                                 'strong',
                                 'em',
                                 'b',
                                 'code',
                                 'i',
                                 'ul',
                                 'ol',
                                 'li',
                                 'p',
                                 'table',
                                 'caption',
                                 'tr',
                                 'td',
                                 'span',
                                 'a',
                                 'blockquote',
                                 'pre',
                                 'iframe',
                                 'h1', 'h2', 'h3', 'address'
                                 );
    // attributes which should be checked for valid protocols
    public $protocol_attributes = array(
                                        'src',
                                        'href',
                                        );
    // protocols which are allowed
    public $allowed_protocols = array(
                                      'http',
                                      'https',
                                      'ftp',
                                      'mailto',
                                      );
    // tags which should be removed if they contain no content
    // (e.g. "<b></b>" or "<b />")
    public $remove_blanks = array(
                                  'p',
                                  'strong',
                                  'em',
                                  'caption',
                                  'li',
                                  'span',
                                  );
}
