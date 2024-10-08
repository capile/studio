<?php
/**
 * Studio sub-template
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

if(isset($error)): 
    ?><div class="s-msg s-msg-error"><?php echo $error; ?></div><?php 
endif;

if(isset($preview)) echo $preview;

if(isset($list))
    echo 
        $listCounter,
        ((isset($searchForm))?('<input type="checkbox" id="s-api-s-'.$id.'" class="s-switch s-api-search" /><label for="s-api-s-'.$id.'">'.$Api::t('labelFilter').'</label><div class="s-api-search s-switched">'.$searchForm.'</div>'):('')),
        $list; 
