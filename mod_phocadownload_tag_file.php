<?php  
/*
 * @package		Joomla.Framework
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @component   Phoca Component
 * @copyright   Copyright (C) Jan Pavelka www.phoca.cz
 * @license     http://www.gnu.org/copyleft/gpl.html GNU General Public License version 2 or later
 */

defined( '_JEXEC' ) or die( 'Restricted access' ); // no direct access

if ( !JComponentHelper::isEnabled( 'com_phocadownload', true ) ) {
	return JError::raiseError( JText::_( 'Phoca Download Error' ), JText::_( 'Phoca Download is not installed on your system' ) );
}
require_once( JPATH_BASE . DS . 'components' . DS . 'com_phocadownload' . DS . 'helpers' . DS . 'phocadownload.php' );
require_once( JPATH_BASE . DS . 'components' . DS . 'com_phocadownload' . DS . 'helpers' . DS . 'route.php' );
require_once( JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_phocadownload' . DS . 'helpers' . DS . 'phocadownload.php' );


$user 		=& JFactory::getUser();
$db 		=& JFactory::getDBO();
$menu 		=& JSite::getMenu();
$document	=& JFactory::getDocument();
$user 		= JFactory::getUser();
$userLevels	= implode( ',', $user->authorisedLevels() );


// CSS Image Path
$imagePath		= PhocaDownloadHelper::getPathSet( 'icon' );
$cssImagePath	= str_replace( '../', JURI::base( true ) . '/', $imagePath[ 'orig_rel_ds' ] );

// Component Params
$component 	        = 'com_phocadownload';
$paramsC	        = JComponentHelper::getParams( $component ) ;
$theme		        = $paramsC->get( 'theme', 'phocadownload-grey' );
$displayFileView    = $paramsC->get( 'display_file_view', '0' );
$buttonStyle	    = $paramsC->get( 'button_style', 'rc' );


// Theme
JHTML::stylesheet( 'components/com_phocadownload/assets/phocadownload.css' );
JHTML::stylesheet( 'components/com_phocadownload/assets/'.$theme.'.css' );
JHTML::stylesheet( 'components/com_phocadownload/assets/phocadownloadbutton.css' );
if ($buttonStyle == 'rc') {
	JHTML::stylesheet( 'components/com_phocadownload/assets/phocadownloadbuttonrc.css' );
}
JHTML::stylesheet( 'components/com_phocadownload/assets/custom.css' );
$document->addStyleSheet( JURI::base( true ) . '/modules/mod_phocadownload_tag_file/assets/phocadownloadfile.css' );		

// Module Params
$moduleType 		= $params->get( 'module_type', 0 );
$categories 		= $params->get( 'category_ids', '' );

$tags 		= $params->get( 'tags', '' );

$ordering			= $params->get( 'file_ordering', 6 );
$fileCount			= $params->get( 'file_count', 5 );
$fileNovelty		= $params->get( 'file_novelty', 0 );
$fileIconSize		= $params->get( 'file_icon_size', 16 );
$displayNew			= $params->get( 'display_new', 0 );
$displayHot			= $params->get( 'display_hot', 0 );
$displayIcon1		= $params->get( 'display_icon1', 0 );
$displayIcon2		= $params->get( 'display_icon2', 0 );
$showHits   		= $params->get( 'show_hits', 0 );
$displayCatInfo		= $params->get( 'display_cat_info' , 0);
$displayFileIcon	= $params->get( 'display_file_icon' , 1);
$feedFormat			= $params->get( 'feed_format', 'rss' );
$feedLinkTitle		= $params->get( 'feed_link_title', JText::_('MOD_PHOCADOWNLOAD_FILE_FEED_ENTRIES'));
$displayDateType	= (int)$params->get( 'display_date_type_file', 10 ); // only for file not for RSS
$displayFileTitle	= $params->get( 'display_file_title', 0 );
$displayDesc		= $params->get( 'display_description', 0 );
$descCharacters		= $params->get( 'description_characters', 200);
$linkToFile			= $params->get( 'link_to_file', 0 );


$wheres = array();
/*
if (is_array($categories) && count($categories) > 0) {
	JArrayHelper::toInteger($categories);
	$categoriesString	= implode(',', $categories);
	$wheres[]	= ' c.catid IN ( '.$categoriesString.' ) ';
} else if ((int)$categories > 0) {
	$wheres[]	= ' c.catid IN ( '.$categories.' ) ';
}

*/

if (is_array($tags) && count($tags) > 0) {
	JArrayHelper::toInteger($tags);
	$tagsString	= implode(',', $tags);
	$wherestag	= ' AND tr.tagid IN ( '.$tagsString.' ) ';
} else if ((int)$tags > 0) {
	$wherestag	= ' AND tr.tagid IN ( '.$tags.' ) ';
}


/*
$wheres[]	= ' c.catid= cc.id';
*/

$wheres[] = ' ( (unaccessible_file = 1 ) OR (unaccessible_file = 0 AND c.access IN (' . $userLevels . ') ) )';
$wheres[] = ' ( (unaccessible_file = 1 ) OR (unaccessible_file = 0 AND cc.access IN (' . $userLevels . ') ) )';
		
$wheres[] = ' c.published = 1';
$wheres[] = ' c.approved = 1';
$wheres[] = ' cc.published = 1';
$wheres[] = ' c.textonly = 0';

// Select active and most recent files
$jnow		=& JFactory::getDate();
$now		= $jnow->toMySQL(); // ToDo: do not count server time offset - check
$nullDate	= $db->getNullDate();

$wheres[] = ' ( c.publish_up = ' . $db->Quote($nullDate) . ' OR c.publish_up <= ' . $db->Quote($now) . ' )';
$wheres[] = ' ( c.publish_down = ' . $db->Quote($nullDate) . ' OR c.publish_down >= ' . $db->Quote($now) . ' )';
if ( $fileNovelty ) {
    $wheres[] = ' ( ( TO_DAYS(' . $db->Quote($now) . ') - TO_DAYS( c.date ) ) <= ' . $db->Quote($fileNovelty) . ')';
}


$fileOrdering	= PhocaDownloadHelperFront::getOrderingText($ordering);

$query =  'SELECT c.*, cc.id AS categoryid, cc.title AS categorytitle, cc.alias AS categoryalias, cc.access as cataccess, cc.accessuserid as cataccessuserid, tr.tagid as tagids, tt.title as tagtitle'
		. ' FROM #__phocadownload AS c'
		. ' LEFT JOIN #__phocadownload_tags_ref AS tr ON tr.fileid = c.id'
		. ' LEFT JOIN #__phocadownload_tags AS tt ON tt.id = tr.tagid'
		. ' LEFT JOIN #__phocadownload_categories AS cc ON cc.id = c.catid'
		. ' WHERE ' . implode( ' AND ', $wheres ).$wherestag
		. ' GROUP BY tr.fileid '
		. ' ORDER BY c.' . $fileOrdering;

$db->setQuery( $query , 0, $fileCount );	
$files = $db->loadObjectList( );


$output = ''; // stores module's HTML code

if ( ( $moduleType == 0 || $moduleType == 2 ) && count( $files ) ) {
	
	foreach ( $files as $keyDoc => $valueDoc ) {


		if ( $valueDoc->filename != '' ) {
		
			// USER RIGHT - Access of categories (if file is included in some not accessed category) - - - - -
			// ACCESS is handled in SQL query, ACCESS USER ID is handled here (specific users)
			$rightDisplay = 0;
			if ( !empty( $valueDoc ) ) {
				$rightDisplay = PhocaDownloadHelper::getUserRight( 'accessuserid', $valueDoc->cataccessuserid, $valueDoc->cataccess, $user->authorisedLevels(), $user->get( 'id', 0 ), 0 );
			}
			// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
			if ( $rightDisplay == 1 ) {
				
				$output .= '<div class="pd-mf-box">';
								
				// TITLE - - - - - - - - - - -
				if ( $displayFileTitle == 1 ) {
					if ( $valueDoc->title !='' ) {
						$output .= '<div class="pd-mf-title">' . $valueDoc->title . ($showHits ? '<span class="pd-mf-hits"> (' . $valueDoc->hits .')</span>' : '') . '</div>' ;
					}
				}
				
                // ICON  - - - - - - - - - - -
				if ( $displayFileIcon ) {

                    $imageFileName = '';
                    $imageFileNameThumb = '';

                    if ( $valueDoc->image_filename !='' ) { // assigned by user
                        $thumbnail = false;
                        $thumbnail = preg_match( "/phocathumbnail/i", $valueDoc->image_filename );

                        if ( $thumbnail ) {
                            $imageFileNameThumb = '<div style="margin-top:5px; margin-bottom:5px" ><img src="' . $cssImagePath . $valueDoc->image_filename . '" alt="" /></div>';
                        } else {
                            $imageFileName = 'style="background: url(\'' . $cssImagePath . $valueDoc->image_filename . '\') 0 center no-repeat;"';
                        }
                    } else { // by extension
                        $ext = JFile::getExt($valueDoc->filename );
                        $icon = 'icon-' . $ext . '-' . $fileIconSize . '.png';
                        $fullPath = JPATH_ROOT . DS . 'modules' . DS . 'mod_phocadownload_tag_file' . DS . 'assets' . DS . 'images' . DS . $icon;
						$relPath = JURI::base( false ) . '/modules/mod_phocadownload_tag_file/assets/images/' . $icon;
						
                        if ( JFile::exists( $fullPath ) ) {
						   $imageFileName = 'style="background: url(\'' . $relPath . '\') 0 center no-repeat;"';
                        }
                    }

					$output .= '<div class="pdfile">' . $imageFileNameThumb . '<div class="pd-document' . $fileIconSize . '" '.$imageFileName . '><div class="pd-float">';				

				} else { // no icon
					$output .= '<div class="pdfileni"><div class="pd-documentni' . $fileIconSize . '" ><div class="pd-float">';
				}
			
				// URL - - - - - - - - - - - -
                if ( $linkToFile ) {
                    if ( $displayFileView && $linkToFile == 2 ) { // link to file's view page
                        $route = JRoute::_( PhocaDownloadHelperRoute::getFileRoute( $valueDoc->id, $valueDoc->categoryid, $valueDoc->alias, $valueDoc->categoryalias, $valueDoc->sectionid ) );
                    } else { // direct download link
                        $route = JRoute::_( PhocaDownloadHelperRoute::getFileRoute( $valueDoc->id, $valueDoc->categoryid, $valueDoc->alias, $valueDoc->categoryalias, $valueDoc->sectionid, 'download' ) );
                    }
				} else {
					//$route = JRoute::_( PhocaDownloadHelperRoute::getCategoryRoute( $valueDoc->categoryid,$valueDoc->categoryalias, $valueDoc->sectionid ) );
					$route = JRoute::_( PhocaDownloadHelperRoute::getCategoryRouteByTag($valueDoc->tagids ) );
				}
				
				

				
	

                if ( $displayFileTitle ) { // file name with link
					
						if ( !$linkToFile && $displayCatInfo) {
						
							$output .= PhocaDownloadHelper::getTitleFromFilenameWithExt( $valueDoc->filename );
							
						}else{
						
							$output .= '<a href="' . $route . '" ' . 'title="' . $valueDoc->title . '">' . PhocaDownloadHelper::getTitleFromFilenameWithExt( $valueDoc->filename	 ) .'</a>';
							
						}
					
                } else { // file title with link
				
						if ( !$linkToFile && $displayCatInfo) {
							
							$output .= $valueDoc->title . ($showHits ? '<span class="pd-mf-hits"> (' . $valueDoc->hits .')</span>' : '');
							
						}else{
						
							$output .= '<a href="' . $route . '" ' . 'title="' . $valueDoc->title . '">' . $valueDoc->title  . '</a>' . ($showHits ? '<span class="pd-mf-hits"> (' . $valueDoc->hits .')</span>' : '');
							
						}
					
                }
			
				// CATEGORY INFO - - - - - - -
                if ( $displayCatInfo == 1 ) {
					
					$query =  'SELECT t.* FROM #__phocadownload_tags AS t LEFT JOIN #__phocadownload_tags_ref AS tr ON t.id=tr.tagid WHERE fileid='.$valueDoc->id;
					$db->setQuery( $query);	
					$tagnames = $db->loadObjectList( );
					
					$tagnamestre = "";
					
					for($i=0;$i<count($tagnames);$i++){
						
							$tagroute = JRoute::_( PhocaDownloadHelperRoute::getCategoryRouteByTag($tagnames[$i]->id ) );
						
						if($linkToFile){
						
							$tagnamestrehrf = $tagnames[$i]->title;
							
						}else{
						
							$tagnamestrehrf = '<a href="'.$tagroute.'">'.$tagnames[$i]->title.'</a>';
							
						}
						
						$tagnamestre = 	$tagnamestre.$tagnamestrehrf;
						

						if($i != (count($tagnames)-1)){
						
							$tagnamestre = $tagnamestre.", ";
							
						}
						
					}
					
					if($tagnamestre){
					
						$output .=	' <span class="pd-mf-category">(' .$tagnamestre . ')</span>';
						
					}
				}
								
				$output .= '</div>';
				
                // NEW/HOT ICONS - - - - - - -
				$output .= PhocaDownloadHelper::displayNewIcon( $valueDoc->date, $displayNew );
				$output .= PhocaDownloadHelper::displayHotIcon( $valueDoc->hits, $displayHot );
				
				// SPECIFIC ICONS  - - - - - -
				if ( $displayIcon1 == 1 ) {
					if (isset($valueDoc->image_filename_spec1) && $valueDoc->image_filename_spec1 != '') {
						$iconPath	= PhocaDownloadHelper::getPathSet('icon');
						$iconPath	= str_replace ( '../', JURI::base(true).'/', $iconPath['orig_rel_ds']);
						$output .= '<div class="pd-float"><img src="'.$iconPath . $valueDoc->image_filename_spec1.'" alt="" /></div>';
					}
				}
				if ( $displayIcon2 == 1 ) {
					if (isset($valueDoc->image_filename_spec2) && $valueDoc->image_filename_spec2 != '') {
						$iconPath	= PhocaDownloadHelper::getPathSet('icon');
						$iconPath	= str_replace ( '../', JURI::base(true).'/', $iconPath['orig_rel_ds']);
						$output .= '<div class="pd-float"><img src="'.$iconPath . $valueDoc->image_filename_spec2.'" alt="" /></div>';
					}
				}
				
				$output .= '</div>' . "\n";
				
				// DATE - - - - - - - - - - -
				$fileDate = '';
				if ( $displayDateType ) {
					if ( $displayDateType > 1 ) {
						if ( $valueDoc->filename !='' ) {
							$fileDate = PhocaDownloadHelper::getFileTime( $valueDoc->filename, $displayDateType, "d F Y H:i" );
						}
					} else { // database time
						$fileDate = JHTML::Date( $valueDoc->date, "d F Y H:i" );
					}
					
					if ( $fileDate != '' ) {
						//$img     = JURI::base(true) . '/modules/mod_phocadownload_tag_file/assets/images/icon-date.png';
						$output .= '<div class="pd-mf-date' . ( $displayFileIcon ? $fileIconSize : '' ) . '"><div class="pd-float">' . $fileDate .  '</div></div>';
					}
				}
				
                // DESCRIPTION - - - - - - - -
				if ( $displayDesc == 1 ) {
				
					$desc	= PhocaDownloadHelper::strTrimAll($valueDoc->description);
					$desc 	= strip_tags( $desc );
					if (JString::strlen($desc) < $descCharacters || JString::strlen($desc) == $descCharacters) {
					} else {
						$desc = JString::substr($desc, 0, $descCharacters) . '...';
					}
				
					$output .= '<div class="pd-mf-desc">'.$desc. '</div>';
				}
				
				$output .= '</div>' . "\n";
				$output .= '</div>';// end pd-mf-box
			}
		}
	}
}


// RSS Feed
if ( ( $moduleType == 1 || $moduleType == 2 ) ) {
	
	if ( $moduleType == 2 ) {
		$output .= '<div>&nbsp;</div>';
	}
		
	// Try to find some Itemid
	if( count( $files ) ) {
		
		foreach ( $files as $keyDoc => $valueDoc ) {
			$link	= JRoute::_( PhocaDownloadHelperRoute::getFeedRoute( (int)$module->id, $valueDoc->categoryid, $valueDoc->sectionid, $feedFormat ) );
			break;
		}
	} else {
		$link	= JRoute::_( PhocaDownloadHelperRoute::getFeedRoute( (int)$module->id, 0, 0, $feedFormat ) );
	}

	if ( $document->direction == 'rtl' ) {
		$img	= JURI::base(true) . '/modules/mod_phocadownload_tag_file/assets/images/feed-rtl.png';
		$output .= '<div><a href="' . $link . '" >' . $feedLinkTitle . '</a> <img src="' . $img . '" alt="" /></div>';
	} else {
		$img	= JURI::base(true) . '/modules/mod_phocadownload_tag_file/assets/images/feed.png';
		$output .= '<div><img src="' . $img . '" alt="" /> <a href="' . $link . '" >' . $feedLinkTitle . '</a></div>';
	}
}


require( JModuleHelper::getLayoutPath( 'mod_phocadownload_tag_file' ) );