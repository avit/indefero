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
                            'img' => array('src', 'class', 'alt', 'height', 'width'),
                            'strong' => array(),
                            'em' => array(),
                            'b' => array(),
                            'i' => array(),
                            'ul' => array(),
                            'ol' => array(),
                            'li' => array(),
                            'p' => array('align', 'class'),
                            'div' => array('align', 'class'),
                            'br' => array(),
                            'pre' => array(),
                            'table' => array('summary'),
                            'caption' => array(),
                            'tr' => array(),
                            'td' => array('style'),
                            'h1' => array(),
                            'h2' => array(),
                            'h3' => array(),
                            'hr' => array(),
                            'address' => array(),
                            'a' => array('href', 'title', 'rel'),
                            'blockquote' => array(),
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
