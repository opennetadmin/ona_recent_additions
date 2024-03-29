<?php
global $base, $conf, $baseURL, $images;

$title_right_html = '';
$title_left_html  = '';
$modbodyhtml = '';
$modjs = '';

// Get info about this file name
$onainstalldir = dirname($base);
$file = str_replace($onainstalldir.'/www', '', __FILE__);
$thispath = dirname($file);

// future config options
$refresh_interval = '600000'; // every 10 minutes
$boxheight = '300px';
$divid = 'ona_recent_additions';

// Display only on the desktop
if ($extravars['window_name'] == 'html_desktop') {

    $title_left_html .= <<<EOL
        Recent ONA Additions
EOL;

    $title_right_html .= <<<EOL
        <a title="Reload recent additions info" onclick="el('{$divid}').innerHTML = '<center>Reloading...</center>';xajax_window_submit('{$file}', ' ', 'ona_recent_additions_list');"><img src="{$images}/silk/arrow_refresh.png" border="0"></a>
EOL;


    $modbodyhtml .= <<<EOL
<div id="{$divid}" style="height: {$boxheight};overflow-y: auto;overflow-x:hidden;font-size:small">
{$conf['loading_icon']}
</div>
EOL;


    // run the function that will update the content of the plugin. update it every 5 mins
    $modjs .= "xajax_window_submit('{$file}', ' ', 'ona_recent_additions_list');setInterval('el(\'{$divid}\').innerHTML = \'{$conf['loading_icon']}\';xajax_window_submit(\'{$file}\', \' \', \'ona_recent_additions_list\');',{$refresh_interval});";

$divid='';

}







/*
Gather information about recent additions and display them
*/
function ws_ona_recent_additions_list($window_name, $form='') {
    global $conf, $self, $onadb, $base, $images, $baseURL;

    // Get info about this file name
    $onainstalldir = dirname($base);
    $file = str_replace($onainstalldir.'/www', '', __FILE__);
    $thispath = dirname($file);

    // If an array in a string was provided, build the array and store it in $form
    $form = parse_options_string($form);


    // Get recent subnets
    list ($status, $rows, $subnets) = db_get_records($onadb,'subnets','id > 0',"id DESC", 5, 0);
    foreach ($subnets as $subnet) {
        $subnet['ip_addr'] = ip_mangle($subnet['ip_addr'], 'dotted');
        $subnet['ip_mask'] = ip_mangle($subnet['ip_mask'], 'cidr');
        $htmllines .= <<<EOL
    <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
        <td align=right class="list-row">SUBNET:</td>
        <td class="list-row">
          <a title="View subnet. ID: {$subnet['id']}"
             class="nav"
             onClick="xajax_window_submit('work_space', 'xajax_window_submit(\'display_subnet\', \'subnet_id=>{$subnet['id']}\', \'display\')');"
          >{$subnet['name']}</a>
        </td>
        <td class="list-row">{$subnet['ip_addr']}/{$subnet['ip_mask']}</td>
        <td class="list-row">&nbsp;</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
    </tr>
EOL;
    }


    // Get recent hosts
    list ($status, $rows, $hosts) = db_get_records($onadb,'hosts','id > 0',"id DESC", 5, 0);
    foreach ($hosts as $host) {
      list($status, $rows, $dnsrecord) = ona_get_dns_record(array('id' => $host['primary_dns_id']));
      list($status, $rows, $int) = ona_get_interface_record(array('id' => $dnsrecord['interface_id']));
      list($status, $rows, $dev) = ona_get_device_record(array('id' => $host['device_id']));
      list($status, $rows, $devtype) = ona_get_device_type_record(array('id' => $dev['device_type_id']));
      if ($devtype['id']) {
         list($status, $rows, $model) = ona_get_model_record(array('id' => $devtype['model_id']));
         list($status, $rows, $role)  = ona_get_role_record(array('id' => $devtype['role_id']));
         list($status, $rows, $manu)  = ona_get_manufacturer_record(array('id' => $model['manufacturer_id']));
         $devtype_desc = "{$manu['name']}, {$model['name']} ({$role['name']})";
      }

        $host['ip_addr'] = ip_mangle($int['ip_addr'], 'dotted');
        $htmllines .= <<<EOL
    <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
        <td align=right class="list-row">HOST:</td>
        <td class="list-row">
          <a title="View host. ID: {$host['id']}"
             class="nav"
             onClick="xajax_window_submit('work_space', 'xajax_window_submit(\'display_host\', \'host_id=>{$host['id']}\', \'display\')');"
          >{$dnsrecord['name']}</a
          >.<a title="View domain. ID: {$dnsrecord['domain_id']}"
               class="domain"
               onClick="xajax_window_submit('work_space', 'xajax_window_submit(\'display_domain\', \'domain_id=>{$dnsrecord['domain_id']}\', \'display\')');"
          >{$dnsrecord['domain_fqdn']}</a>
        </td>
        <td class="list-row">{$host['ip_addr']}</td>
        <td class="list-row">{$devtype_desc}&nbsp;</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
    </tr>
EOL;
    }



    // Get recent dns records
    list ($status, $rows, $dnsrecords) = db_get_records($onadb,'dns','id > 0',"id DESC", 5, 0);
    foreach ($dnsrecords as $dns) {
      //list($status, $rows, $dnsrecord) = ona_get_dns_record(array('id' => $host['primary_dns_id']));
      list($status, $rows, $int) = ona_get_interface_record(array('id' => $dns['interface_id']));
      list($status, $rows, $domain) = ona_get_domain_record(array('id' => $dns['domain_id']));

      $dns['ip_addr'] = ip_mangle($int['ip_addr'], 'dotted');

      if ($dns['type'] == 'SRV') {
        list($status, $rows, $pointsto) = ona_get_dns_record(array('id' => $dns['dns_id']), '');
        $dns['type'] = $dns['type'].'('.$dns['srv_port'].')';
        $dns['fqdn'] = $dns['name'].'.'.$domain['fqdn'];
        $dns['ip_addr'] = $pointsto['fqdn'];
      }
      elseif ($dns['type'] == 'TXT') {
        $dns['fqdn'] = $dns['name'].'.'.$domain['fqdn'];
        $dns['ip_addr'] = $dns['txt'];
      }
      // Make PTR look better
      elseif ($dns['type'] == 'PTR') {
        list($status, $rows, $pointsto) = ona_get_dns_record(array('id' => $dns['dns_id']), '');
        list($status, $rows, $pdomain)  = ona_get_domain_record(array('id' => $dns['domain_id']), '');

        // Flip the IP address
        $dns['name'] = ip_mangle($dns['ip_addr'],'flip');
        $dns['domain'] = $pdomain['name'];

        if ($pdomain['parent_id']) {
            list ($status, $rows, $parent) = ona_get_domain_record(array('id' => $pdomain['parent_id']));
            $parent['name'] = ona_build_domain_name($parent['id']);
            $dns['domain'] = $pdomain['name'].'.'.$parent['name'];
            unset($parent['name']);
        }

        // strip down the IP to just the "host" part as it relates to the domain its in
        if (strstr($dns['domain'],'in-addr.arpa')) {
            $domain_part = preg_replace("/.in-addr.arpa$/", '', $dns['domain']);
        } else {
            $domain_part = preg_replace("/.ip6.arpa$/", '', $dns['domain']);
        }
        $dns['fqdn'] = preg_replace("/${domain_part}$/", '', $dns['name']).$dns['domain'];
        $dns['ip_addr'] = $pointsto['fqdn'];
      }
      elseif ($dns['type'] == 'CNAME') {
        list($status, $rows, $pointsto) = ona_get_dns_record(array('id' => $dns['dns_id']), '');
        $dns['ip_addr'] = $pointsto['fqdn'];
        $dns['fqdn'] = $dns['name'].'.'.$domain['fqdn'];

      } else {
        $dns['fqdn'] = $dns['name'].'.'.$domain['fqdn'];
      }

        $htmllines .= <<<EOL
    <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
        <td align=right class="list-row">DNS:</td>
        <td class="list-row">{$dns['fqdn']}&nbsp;</td>
        <td class="list-row">{$dns['type']}</td>
        <td class="list-row">
          <a title="View host. ID: {$int['host_id']}"
             class="nav"
             onClick="xajax_window_submit('work_space', 'xajax_window_submit(\'display_host\', \'host_id=>{$int['host_id']}\', \'display\')');"
          >{$dns['ip_addr']}</a>
        </td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
    </tr>
EOL;
    }



    // If we actually have information.. print the table
    if (!$htmllines) {
        $htmllines = "<tr><td>There was an error gathering data.</td></tr>";
    }
    $html .= '<table class="list-box" cellspacing="0" border="0" cellpadding="0">';
    $html .= $htmllines;
    $html .= "</table>";



    // Insert the new table into the window
    $response = new xajaxResponse();
    $response->assign('ona_recent_additions', "innerHTML", $html);
    $response->script($js);
    return($response);
}






?>
