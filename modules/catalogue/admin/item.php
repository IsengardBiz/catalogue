<?php
/**
* Admin page to manage items
*
* List, add, edit and delete item objects
*
* @copyright	Copyright Madfish (Simon Wilkinson)
* @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
* @since		1.0
* @author		Madfish (Simon Wilkinson) <simon@isengard.biz>
* @package		catalogue
* @version		$Id$
*/

/**
 * Edit a Item
 *
 * @param int $item_id Itemid to be edited
*/
function edititem($item_id = 0)
{
	global $catalogue_item_handler, $icmsUser, $icmsAdminTpl;
	
	$catalogueModule = icms_getModuleInfo(basename(dirname(dirname(__FILE__))));

	$itemObj = $catalogue_item_handler->get($item_id);

	if (!$itemObj->isNew()){
		$itemObj->loadTags();
		$catalogueModule->displayAdminMenu(0, _AM_CATALOGUE_ITEMS . " > " . _CO_ICMS_EDITING);
		$sform = $itemObj->getForm(_AM_CATALOGUE_ITEM_EDIT, 'additem');
		$sform->assign($icmsAdminTpl);

	} else {
		$itemObj->setVar('submitter', $icmsUser->getVar('uid'));
		$catalogueModule->displayAdminMenu(0, _AM_CATALOGUE_ITEMS . " > " . _CO_ICMS_CREATINGNEW);
		$sform = $itemObj->getForm(_AM_CATALOGUE_ITEM_CREATE, 'additem');
		$sform->assign($icmsAdminTpl);

	}
	$icmsAdminTpl->display('db:catalogue_admin_item.html');
}

include_once("admin_header.php");

$clean_op = '';
$catalogue_item_handler = icms_getModuleHandler('item');

/** Create a whitelist of valid values, be sure to use appropriate types for each value
 * Be sure to include a value for no parameter, if you have a default condition
 */
$valid_op = array ('mod','changedField','additem','del','view','visible', 'changeWeight', '');

if (isset($_GET['op'])) $clean_op = htmlentities($_GET['op']);
if (isset($_POST['op'])) $clean_op = htmlentities($_POST['op']);

/** Again, use a naming convention that indicates the source of the content of the variable */
$clean_item_id = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0 ;
$clean_tag_id = isset($_GET['tag_id']) ? intval($_GET['tag_id']) : 0 ;

/**
 * in_array() is a native PHP function that will determine if the value of the
 * first argument is found in the array listed in the second argument. Strings
 * are case sensitive and the 3rd argument determines whether type matching is
 * required
*/
if (in_array($clean_op,$valid_op,true)){
  switch ($clean_op) {
  	case "mod":
  	case "changedField":

  		icms_cp_header();

  		edititem($clean_item_id);
  		break;
  	case "additem":
        include_once ICMS_ROOT_PATH."/kernel/icmspersistablecontroller.php";
        $controller = new IcmsPersistableController($catalogue_item_handler);
  		$controller->storeFromDefaultForm(_AM_CATALOGUE_ITEM_CREATED, _AM_CATALOGUE_ITEM_MODIFIED);

  		break;

  	case "del":
  	    include_once ICMS_ROOT_PATH."/kernel/icmspersistablecontroller.php";
        $controller = new IcmsPersistableController($catalogue_item_handler);
  		$controller->handleObjectDeletion();

  		break;

  	case "view":
  		$itemObj = $catalogue_item_handler->get($clean_item_id);
  		icms_cp_header();
  		$itemObj->displaySingleObject();

  		break;

	case "visible":
		$visibility = $catalogue_item_handler->changeVisible($clean_item_id);
		$ret = '/modules/' . basename(dirname(dirname(__FILE__))) . '/admin/item.php';
		if ($visibility == 0) {
			redirect_header(ICMS_URL . $ret, 2, _AM_CATALOGUE_ITEM_INVISIBILE);
		} else {
			redirect_header(ICMS_URL . $ret, 2, _AM_CATALOGUE_ITEM_VISIBILE);
		}
		
		break;
	
	case "changeWeight":
		foreach ($_POST['CatalogueItem_objects'] as $key => $value) {
			$changed = false;
			$itemObj = $catalogue_item_handler->get($value);

			if ($itemObj->getVar('weight', 'e') != $_POST['weight'][$key]) {
				$itemObj->setVar('weight', intval($_POST['weight'][$key]));
				$changed = true;
			}
			if ($changed) {
				$catalogue_item_handler->insert($itemObj);
			}
		}
		$ret = '/modules/' . basename(dirname(dirname(__FILE__))) . '/admin/item.php';
		redirect_header(ICMS_URL . $ret, 2, _AM_CATALOGUE_ITEM_WEIGHTS_UPDATED);

		break;

  	default:
  		icms_cp_header();

  		$catalogueModule->displayAdminMenu(0, _AM_CATALOGUE_ITEMS);
		
		$criteria = '';
		
		// display a tag select filter (if the Sprockets module is installed)
		$sprocketsModule = icms_getModuleInfo('sprockets');
		
		// if no op is set, but there is a (valid) soundtrack_id, display a single object
		if ($clean_item_id) {
			$itemObj = $catalogue_item_handler->get($clean_item_id);
			if ($itemObj->id()) {
				
				// prepare item image for display
				$image = $itemObj->getVar('image', 'e');
				if ($image) {
					$image = '<img src="/uploads/' . basename(dirname(dirname(__FILE__)))
						. '/item/' . $image . '" alt="' . $itemObj->title() . '" />';
					$itemObj->setVar('image', $image);
				}

				// prepare submitter for display
				$user = catalogue_getLinkedUnameFromId($itemObj->getVar('submitter', 'e'));
				$itemObj->setVar('submitter', $user);
				$itemObj->displaySingleObject();
			}
		}
		
		if ($sprocketsModule) {
			
			$tag_select_box = '';
			$taglink_array = $tagged_item_list = array();
			$sprockets_tag_handler = icms_getModuleHandler('tag', $sprocketsModule->dirname(),
				'sprockets');
			$sprockets_taglink_handler = icms_getModuleHandler('taglink', 
					$sprocketsModule->dirname(), 'sprockets');
			$catalogueModule = icms_getModuleInfo(basename(dirname(dirname(__FILE__))));
			
			$tag_select_box = $sprockets_tag_handler->getTagSelectBox('item.php', $clean_tag_id,
				_AM_CATALOGUE_ITEM_ALL_ITEMS);
			if (!empty($tag_select_box)) {
				echo '<h3>' . _AM_CATALOGUE_ITEM_FILTER_BY_TAG . '</h3>';
				echo $tag_select_box;
			}
			
			if ($clean_tag_id) {
				
				// get a list of item IDs belonging to this tag
				$criteria = new CriteriaCompo();
				$criteria->add(new Criteria('tid', $clean_tag_id));
				$criteria->add(new Criteria('mid', $catalogueModule->mid()));
				$criteria->add(new Criteria('item', 'item'));
				$taglink_array = $sprockets_taglink_handler->getObjects($criteria);
				foreach ($taglink_array as $taglink) {
					$tagged_item_list[] = $taglink->getVar('iid');
				}
				$tagged_item_list = "('" . implode("','", $tagged_item_list) . "')";
				
				// use the list to filter the persistable table
				$criteria = new CriteriaCompo();
				$criteria->add(new Criteria('item_id', $tagged_item_list, 'IN'));
			}
		}
		
		if (empty($criteria)) {
			$criteria = null;
		}

  		include_once ICMS_ROOT_PATH."/kernel/icmspersistabletable.php";
  		$objectTable = new IcmsPersistableTable($catalogue_item_handler, $criteria);
		$objectTable->addColumn(new IcmsPersistableColumn('online_status', 'center', true));
  		$objectTable->addColumn(new IcmsPersistableColumn('title'));
		$objectTable->addColumn(new IcmsPersistableColumn('identifier'));
		$objectTable->addColumn(new IcmsPersistableColumn('price'));
		$objectTable->addColumn(new IcmsPersistableColumn('counter'));
		$objectTable->addColumn(new IcmsPersistableColumn('weight', 'center', true,
			'getWeightControl'));
		$objectTable->addFilter('online_status', 'online_status_filter');
  		$objectTable->addIntroButton('additem', 'item.php?op=mod', _AM_CATALOGUE_ITEM_CREATE);
		$objectTable->addActionButton('changeWeight', false, _SUBMIT);
  		$icmsAdminTpl->assign('catalogue_item_table', $objectTable->fetch());
  		$icmsAdminTpl->display('db:catalogue_admin_item.html');
  		break;
  }
  icms_cp_footer();
}
/**
 * If you want to have a specific action taken because the user input was invalid,
 * place it at this point. Otherwise, a blank page will be displayed
 */