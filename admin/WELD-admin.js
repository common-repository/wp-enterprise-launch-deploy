jQuery(document).ready(function() {



// Init & reload function

var weld_serverGroup_baseline = {"serverGroupName":"Default","hashID":"Default","siteURL":"","serverTransferType":"ssh","serverAddr":[""],"serverUsername":"","serverPassword":"","serverPort":"","serverTargetDir":"","serverLoadBalancerFile":"","databaseUsername":"","databasePassword":"","databaseServersDifferent":false,"databaseAddr":[""],"databasePort":"","databaseSSL":false,"databaseSSLclientCert":"","databaseSSLclientKey":"","databaseServersDifferent":false,"excludeAdmin":false,"targetPublishURL":""};

	//weld-allowableFields
	if(jQuery("#weld_server_array").attr("value")!=""){
		var weldAllowableFields = jQuery.parseJSON(jQuery("#weld_server_array").attr("value"));
		for (var key in weldAllowableFields) {
			jQuery("#weld-serverGroups table tbody").append(weld_generate_serverSettingBlock(weldAllowableFields[key],key));
		}
		weld_BindFieldUpdates();
		weld_BindAddButtons();
	}else{
		weld_BindFieldUpdates();
		weld_BindAddButtons();
	}



// end Init	

	


// Functions

function weld_generate_serverSettingBlock(serverSettingBlockInput,groupIndex){
	// This function will take a server setting array and output it as html for the admin panel
	if(typeof(settingHTML)=="undefined"){
		var settingHTML = "";
	}else{
		settingHTML = "";
	}

	if(typeof(groupIndex)=="undefined"){
		groupIndex = 1;
	}


	if(typeof(serverSettingBlockInput)=="undefined"){
		var serverSettingBlock = weldClone(weld_serverGroup_baseline);

	}else{

		var serverSettingBlock = weldClone(serverSettingBlockInput);
		// Fill in any missing (aka TOTALLY undefined) settings with defaults
		for (var key in weld_serverGroup_baseline) {
			if(typeof(serverSettingBlock[key])=="undefined"){
		    		serverSettingBlock[key] = weldClone(weld_serverGroup_baseline[key]);
			}
		}
	}
	
	if(serverSettingBlock['hashID'] == "Default" || serverSettingBlock['hashID'] == ""){
		serverSettingBlock['hashID'] = new Date().valueOf();
	}

	if(groupIndex%2==0){
		settingHTML = "<tr id=\"record_"+groupIndex+"\" class=\"format-standard alternate\">\n\t<td><h3>";
	}else{
		settingHTML = "<tr id=\"record_"+groupIndex+"\" class=\"format-standard\">\n\t<td><h3>";
	}


	// Server/Group Name
	settingHTML = settingHTML + groupIndex + ". </h3>\n";
	settingHTML = settingHTML + "\t\t<input type=\"text\" name=\"weldServerGroup["+groupIndex+"][serverGroupName]\" value=\""+serverSettingBlock['serverGroupName']+"\" />\n";
	settingHTML = settingHTML + "\t\t<input type=\"text\" class=\"weld-hiddeninput\" name=\"weldServerGroup["+groupIndex+"][hashID]\" value=\""+serverSettingBlock['hashID']+"\" />\n";
	settingHTML = settingHTML + "\t\t<p><span class=\"weld_annotation\">Server Group ID: "+serverSettingBlock['hashID']+"</span></p>\n";
	settingHTML = settingHTML + "\t\t<p><a class=\"button-secondary weld-serverGroups-deletegroup\" href=\"#\" title=\"Delete Group\">Delete Group</a></p>\n\t</td>\n";


	// Server Settings
	settingHTML = settingHTML + "<td>\n\t<ul>\n\t";
	settingHTML = settingHTML + "<li>\n\t\t<label for=\"weldServerGroup["+groupIndex+"][serverTransferType]\">Transfer Type</label><br/>\n\t\t\t\n";
		if(serverSettingBlock['serverTransferType']=="ftp"){
			settingHTML = settingHTML + "\t\t\t<option value=\"ftp\" selected>FTP</option> \n\t\t\t <option value=\"ssh\" >RSYNC/SSH</option>";
		}else{
			settingHTML = settingHTML + "\t\t\t<option value=\"ftp\">FTP</option> \n\t\t\t<option value=\"ssh\" selected>RSYNC/SSH</option>";
		}

		if(serverSettingBlock['serverTransferType']=="ftp"){
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-serverTransferType\" name=\"weldServerGroup["+groupIndex+"][serverTransferType]\" value=\"ftp\" checked=\"checked\" />FTP\n";
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-serverTransferType\" name=\"weldServerGroup["+groupIndex+"][serverTransferType]\" value=\"ssh\" />RSYNC/SSH\n";
		}else{
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-serverTransferType\" name=\"weldServerGroup["+groupIndex+"][serverTransferType]\" value=\"ftp\" />FTP\n";
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-serverTransferType\" name=\"weldServerGroup["+groupIndex+"][serverTransferType]\" value=\"ssh\" checked=\"checked\" />RSYNC/SSH\n";
		}


	settingHTML = settingHTML + "\n\t<li><label for=\"weldServerGroup["+groupIndex+"][serverUsername]\">Username <span class=\"weld_annotation\">FTP only</span></label><input type=\"text\" class=\"weldServerGroup["+groupIndex+"][serverUsername]\" name=\"weldServerGroup["+groupIndex+"][serverUsername]\" value=\""+serverSettingBlock['serverUsername']+"\"/></li>";
	settingHTML = settingHTML + "\n\t<li><label for=\"weldServerGroup["+groupIndex+"][serverPassword]\">Password <span class=\"weld_annotation\">FTP Only</span></label><input type=\"password\" class=\"weldServerGroup["+groupIndex+"][serverPassword]\" name=\"weldServerGroup["+groupIndex+"][serverPassword]\" value=\""+serverSettingBlock['serverPassword']+"\"/></li>";
	settingHTML = settingHTML + "\n\t<li><label for=\"weldServerGroup["+groupIndex+"][serverPort]\">Port <span class=\"weld_annotation\">(protocol default if blank)</span></label><input type=\"text\" class=\"weldServerGroup["+groupIndex+"][serverPort]\" name=\"weldServerGroup["+groupIndex+"][serverPort]\" value=\""+serverSettingBlock['serverPort']+"\"/></li>";
	settingHTML = settingHTML + "\n\t<li><label for=\"weldServerGroup["+groupIndex+"][serverTargetDir]\">Target Folder <span class=\"weld_annotation\">(same as wordpress install by default)</span></label><input type=\"text\" class=\"weldServerGroup["+groupIndex+"][serverTargetDir]\" name=\"weldServerGroup["+groupIndex+"][serverTargetDir]\" value=\""+serverSettingBlock['serverTargetDir']+"\"/></li>";
	settingHTML = settingHTML + "<li><label for=\"weldServerGroup["+groupIndex+"][serverLoadBalancerFile]\">Load Balancer Heartbeat File</label><input type=\"text\" class=\"weldServerGroup["+groupIndex+"][serverLoadBalancerFile]\" name=\"weldServerGroup["+groupIndex+"][serverLoadBalancerFile]\" value=\""+serverSettingBlock['serverLoadBalancerFile']+"\"/></li>";
	settingHTML = settingHTML + "\n\t</ul>\n\t</td>\n\t";

	// Database Settings
	settingHTML = settingHTML + "\n\t<td>\n\t\t<ul>";
	settingHTML = settingHTML + "\n\t\t\t<li><label for=\"weldServerGroup["+groupIndex+"][databaseUsername]\">Database Username</label><input type=\"text\" class=\"weldServerGroup["+groupIndex+"][databaseUsername]\" name=\"weldServerGroup["+groupIndex+"][databaseUsername]\" value=\""+serverSettingBlock['databaseUsername']+"\"/></li>";
	settingHTML = settingHTML + "\n\t\t\t<li><label for=\"weldServerGroup["+groupIndex+"][databasePassword]\">Database Password</label><input type=\"password\" class=\"weldServerGroup["+groupIndex+"][databasePassword]\" name=\"weldServerGroup["+groupIndex+"][databasePassword]\" value=\""+serverSettingBlock['databasePassword']+"\"/></li>";
	settingHTML = settingHTML + "\n\t\t\t<li><label for=\"weldServerGroup["+groupIndex+"][databasePort]\">Port <span class=\"weld_annotation\">(defaults to 3306)</span></label><input type=\"text\" class=\"weldServerGroup["+groupIndex+"][databasePort]\" name=\"weldServerGroup["+groupIndex+"][databasePort]\" value=\""+serverSettingBlock['databasePort']+"\"/></li>";

	settingHTML = settingHTML + "<li><label for=\"weldServerGroup["+groupIndex+"][databaseSSL]\">Attempt to connect with SSL?</label><br/>";
		if(serverSettingBlock['databaseSSL']==true){
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-databaseSSL\" name=\"weldServerGroup["+groupIndex+"][databaseSSL]\" value=\"true\" checked=\"checked\" />yes\n";
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-databaseSSL\" name=\"weldServerGroup["+groupIndex+"][databaseSSL]\" value=\"false\" />no\n";
		}else{
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-databaseSSL\" name=\"weldServerGroup["+groupIndex+"][databaseSSL]\" value=\"true\" />yes\n";
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-databaseSSL\" name=\"weldServerGroup["+groupIndex+"][databaseSSL]\" value=\"false\" checked=\"checked\" />no\n";
		}

		if(serverSettingBlock['databaseSSL']==false || serverSettingBlock['databaseServersDifferent']=="false"){
				serverSettingBlock['databaseAddr'][key] = serverSettingBlock['serverAddr'][key];
				disabledToggle = "disabled";
			}else{
				disabledToggle = "";
			}

		settingHTML = settingHTML + "\n\t\t\t<li><label for=\"weldServerGroup["+groupIndex+"][databaseSSLclientCert]\">Client Certificate</label><input type=\"text\" class=\"weldServerGroup["+groupIndex+"][databaseSSLclientCert] weld-server-databaseSSLmeta\" name=\"weldServerGroup["+groupIndex+"][databaseSSLclientCert]\" value=\""+serverSettingBlock['databaseSSLclientCert']+"\"  "+disabledToggle+"/></li>";
		settingHTML = settingHTML + "\n\t\t\t<li><label for=\"weldServerGroup["+groupIndex+"][databaseSSLclientKey]\">Client Key</label><input type=\"text\" class=\"weldServerGroup["+groupIndex+"][databaseSSLclientKey] weld-server-databaseSSLmeta\" name=\"weldServerGroup["+groupIndex+"][databaseSSLclientKey]\" value=\""+serverSettingBlock['databaseSSLclientKey']+" \" "+disabledToggle+"/></li>";


	settingHTML = settingHTML + "<li><label for=\"weldServerGroup["+groupIndex+"][databaseServersDifferent]\">Database Server Different from Web Servers?</label>";
		if(serverSettingBlock['databaseServersDifferent']==true){
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-databaseServersDifferent\" name=\"weldServerGroup["+groupIndex+"][databaseServersDifferent]\" value=\"true\" checked=\"checked\" />yes\n";
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-databaseServersDifferent\" name=\"weldServerGroup["+groupIndex+"][databaseServersDifferent]\" value=\"false\" />no\n";
		}else{
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-databaseServersDifferent\" name=\"weldServerGroup["+groupIndex+"][databaseServersDifferent]\" value=\"true\" />yes\n";
			settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" class=\"weld-server-databaseServersDifferent\" name=\"weldServerGroup["+groupIndex+"][databaseServersDifferent]\" value=\"false\" checked=\"checked\" />no\n";
		}
	settingHTML = settingHTML + "\n\t</ul>\n\t</td>\n\t";




	// Server Addresses

	settingHTML = settingHTML + "\n\t<td>\n\t\t<ul class=\"weld-server-address-groups\">";
	settingHTML = settingHTML + "\n\t<li><label for=\"weldServerGroup["+groupIndex+"][targetPublishURL]\">Target Publish URL</label><input type=\"text\" class=\"weldServerGroup["+groupIndex+"][targetPublishURL]\" name=\"weldServerGroup["+groupIndex+"][targetPublishURL]\" value=\""+serverSettingBlock['targetPublishURL']+"\"/></li>";
		var disabledToggle="";
		for (var key in serverSettingBlock['serverAddr']) {
			if(typeof(serverSettingBlock['databaseAddr'][key])=="undefined"){serverSettingBlock['databaseAddr'][key] = "";}

			if(serverSettingBlock['databaseServersDifferent']==false || serverSettingBlock['databaseServersDifferent']=="false"){
				serverSettingBlock['databaseAddr'][key] = serverSettingBlock['serverAddr'][key];
				disabledToggle = "disabled";
			}else{
				disabledToggle = "";
			}
			settingHTML = settingHTML + "\n\t\t\t<li><label for=\"weldServerGroup["+groupIndex+"]["+key+"][serverAddr]\">Web Host<label><a class=\"button-secondary weld-serverGroups-removeServer\" href=\"#\" title=\"Remove Server\">X</a> <input type=\"text\" class=\"weldServerGroup["+groupIndex+"]["+key+"][serverAddr] weld-serverAddr\" name=\"weldServerGroup["+groupIndex+"]["+key+"][serverAddr]\" value=\""+serverSettingBlock['serverAddr'][key]+"\"/> <br /><label for=\"weldServerGroup["+groupIndex+"]["+key+"][databaseAddr]\">Database Host<label><input type=\"text\" class=\"weldServerGroup["+groupIndex+"]["+key+"][databaseAddr]  weld-databaseAddr\" name=\"weldServerGroup["+groupIndex+"]["+key+"][databaseAddr]\" value=\""+serverSettingBlock['databaseAddr'][key]+"\" "+disabledToggle+"/></li>";
		}
	settingHTML = settingHTML + "\n\t\t</ul>\n";
	settingHTML = settingHTML + "\n\t\t<p><a class=\"button-secondary weld-server-add\" data-weldindex="+groupIndex+" href=\"#\" title=\"Add Server\">Add</a></p>";
	settingHTML = settingHTML + "\n\t</td>\n";




	



	// Group Settings
	settingHTML = settingHTML + "\n\t<td>\n\t\t<ul>";
	settingHTML = settingHTML + "<li><label for=\"weldServerGroup["+groupIndex+"][excludeAdmin]\">Hardened Production Server*</label><br />";
	if(serverSettingBlock['excludeAdmin']==true || serverSettingBlock['excludeAdmin'] == "true"){
		settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" name=\"weldServerGroup["+groupIndex+"][excludeAdmin]\" value=\"true\" checked=\"checked\" />yes\n\t\t\t<input type=\"radio\" name=\"weldServerGroup["+groupIndex+"][excludeAdmin]\" value=\"false\" />no\n";
	}else{
		settingHTML = settingHTML + "\n\t\t\t<input type=\"radio\" name=\"weldServerGroup["+groupIndex+"][excludeAdmin]\" value=\"true\" />yes\n\t\t\t<input type=\"radio\" name=\"weldServerGroup["+groupIndex+"][excludeAdmin]\" value=\"false\" checked=\"checked\" />no\n";
	}

	settingHTML = settingHTML + "\t\t\t</li>";
	settingHTML = settingHTML + "\n\t</ul>\n\t</td>\n\t";

	settingHTML = settingHTML + "</tr>\n\t";

	return settingHTML;

}// end weld_generate_serverSettingBlock












// Bind update function to input fields
function weld_BindFieldUpdates(){

	jQuery("#weld-server-settings-form input").bind("change paste keyup",function(){
		weld_scanFieldValues();	
	});

	jQuery("input[name=\"weld_hardening_pluginexcludes_cb\"]").bind("change paste keyup",function(){
		var newSetting = new Array();
		jQuery("input[name=\"weld_hardening_pluginexcludes_cb\"]").each(function(){
				if(jQuery(this).is(':checked')){
					newSetting.push(jQuery(this).attr("value"));
				}
			});
		jQuery("#weld_hardening_pluginexcludes").attr("value",JSON.stringify(newSetting));
	});

}// end weld_BindFieldUpdates


// Bind add buttons
function weld_BindAddButtons(){

	jQuery(".weld-admin-remove-from-queue").unbind("click");
	jQuery(".weld-admin-remove-from-queue").click(function(){
		var queueListFlat = jQuery("#weld_process_list").attr("value");
		var queueListArray = jQuery.parseJSON(queueListFlat);

		var hashID = jQuery(this).attr("data-hash");
		if(parseInt(hashID) != NaN){
			hashID = parseInt(hashID);
		}

		for(var qL in queueListArray){
//console.log(queueListArray[qL]);
			if(queueListArray[qL]['hashID'] == hashID){
				queueListArray[qL] = [];
			}
		}

		//jQuery(".weld-process-queue-tbody").append(appendHTML);
		jQuery(this).closest("tr").remove();
		jQuery("#weld_process_list").attr("value",JSON.stringify(queueListArray));
		//weld-process-queue-tbody
	});


	jQuery(".weld-admin-add-to-queue").unbind("click");
	jQuery(".weld-admin-add-to-queue").click(function(){
		var queueListFlat = jQuery("#weld_process_list").attr("value");
		var queueListArray = new Array;

		if(queueListFlat != "null" && queueListFlat != ""){
			queueListArray = jQuery.parseJSON(queueListFlat);
		}

		var hashID = jQuery(this).attr("data-hash");
		var groupName = jQuery(this).attr("data-groupName");
		if(parseInt(hashID) != NaN){
			hashID = parseInt(hashID);
		}
		var queuedTime = "";
		var lastUpdated = "";
		queueListArray.push({"hashID":hashID,"status":"Pending","queuedTime":queuedTime,"lastUpdated":lastUpdated});

		var appendHTML = "";
		appendHTML = appendHTML +"<tr class=\"format-standard weld_process_queue_new_tr\">";
		appendHTML = appendHTML +"<td>"+groupName+"</td>";
		appendHTML = appendHTML +"<td>Pending</td>";
		appendHTML = appendHTML +"<td>N/A</td>";
		appendHTML = appendHTML +"<td>N/A</td>";
		appendHTML = appendHTML +"<td><p data-hash=\""+hashID+"\" class=\"button-secondary weld-admin-remove-from-queue\">Remove</p></td>";
		appendHTML = appendHTML +"</tr>";

		jQuery(".weld-process-queue-tbody").append(appendHTML);

		jQuery("#weld_process_list").attr("value",JSON.stringify(queueListArray));
		weld_BindAddButtons();
		//weld-process-queue-tbody
	});

	jQuery(".weld-server-add").unbind("click");
	jQuery(".weld-server-add").click(function(){
			if(jQuery(this).closest("tr").find(".weld-server-databaseServersDifferent:checked").attr("value")=="false"){
				disabledToggle = "disabled";
			}else{
				disabledToggle = "";
			}

		var groupIndex = jQuery(this).attr("data-weldindex");
		var key = jQuery(this).parents("td").children("ul").children().length + 1;
		jQuery(this).parents("td").children("ul").last().append("\n\t\t\t<li><input type=\"text\" class=\"weld-serverAddr weldServerGroup["+groupIndex+"]["+key+"][serverAddr]\" name=\"weldServerGroup["+groupIndex+"]["+key+"][serverAddr]\" value=\"\"/> <input type=\"text\" class=\"weld-databaseAddr weldServerGroup["+groupIndex+"]["+key+"][databaseAddr]\" name=\"weldServerGroup["+groupIndex+"]["+key+"][databaseAddr]\" value=\"\" "+disabledToggle+" /> <a class=\"button-secondary weld-serverGroups-removeServer\" href=\"#\" title=\"Remove Server\">X</a></li>");
		weld_BindFieldUpdates();
		weld_BindAddButtons();
	});



	jQuery(".weld-serverGroups-add").unbind("click");
	jQuery(".weld-serverGroups-add").click(function(){
		jQuery("#weld-serverGroups table tbody").append(weld_generate_serverSettingBlock(weld_serverGroup_baseline, jQuery("#weld-serverGroups table tbody > tr").length + 1));
		weld_BindFieldUpdates();
		weld_BindAddButtons();
		weld_scanFieldValues();
	});



	jQuery(".weld-serverGroups-removeServer").unbind("click");
	jQuery(".weld-serverGroups-removeServer").click(function(){
		if(jQuery(this).closest("ul").children().length > 1){
			jQuery(this).closest("li").remove();
			weld_BindAddButtons();
			weld_scanFieldValues();
		}
	});

 
	jQuery(".weld-serverGroups-deletegroup").unbind("click");
	jQuery(".weld-serverGroups-deletegroup").click(function(){
		if(jQuery(this).closest("tr").children().length > 1){
			jQuery(this).closest("tr").remove();
			weld_BindAddButtons();
			weld_scanFieldValues();
		}
	});


	jQuery(".weld-server-databaseServersDifferent").unbind("click");
	jQuery(".weld-server-databaseServersDifferent").click(function(){
		if(jQuery(this).attr("value") == true || jQuery(this).attr("value") =="true"){
			jQuery(this).closest("tr").find("ul.weld-server-address-groups li").each(function(){
				jQuery(this).find(".weld-databaseAddr").prop('disabled', false);
			});
		}else{
			jQuery(this).closest("tr").find("ul.weld-server-address-groups li").each(function(){
				jQuery(this).find(".weld-databaseAddr").attr("value",jQuery(this).find(".weld-serverAddr").attr("value"));
				jQuery(this).find(".weld-databaseAddr").prop('disabled', true);
			});
		}
		weld_BindAddButtons();
		weld_scanFieldValues();
	});


	jQuery(".weld-server-databaseSSL").unbind("click");
	jQuery(".weld-server-databaseSSL").click(function(){
		if(jQuery(this).attr("value") == true || jQuery(this).attr("value") =="true"){
			jQuery(this).closest("td").find(".weld-server-databaseSSLmeta").each(function(){
				jQuery(this).prop('disabled', false);
			});
		}else{
			jQuery(this).closest("td").find(".weld-server-databaseSSLmeta").each(function(){
				jQuery(this).attr("value",jQuery(this).find(".weld-serverAddr").attr("value"));
				jQuery(this).prop('disabled', true);
			});
		}
		weld_BindAddButtons();
		weld_scanFieldValues();
	});


} // end weld_BindAddButtons




function weld_scanFieldValues(){
	var replacementServerObject ={};
	var i = -1;
	var indexRG = new RegExp(/\[([^\[]*)\]/);
	var subIndexRG = new RegExp(/.*\[.*\]\[(.*)\]\[.*\].*/);
	var keynameRG = new RegExp(/.*\[.*\]\[(.*)\]$/);
	var subKeyR = "";
	var subKey = "";
	jQuery("#weld-serverGroups table tbody > tr").each(function(){
		jQuery(this).find("input").each(function(){
			if(i==-1){
				var attrname=jQuery(this).attr('name');
				var newindex = indexRG.exec(attrname);
				i=newindex[1];
				replacementServerObject[i] =  {};
				replacementServerObject[i]['serverAddr'] =  {};
				replacementServerObject[i]['databaseAddr'] =  {};
			}

			var keyR = keynameRG.exec(jQuery(this).attr('name'));
			var key = keyR[1];

			if(key !== "serverAddr" && key !== "databaseAddr"){
				if(jQuery(this).attr('type')=="radio"){
					if(jQuery(this).prop("checked")){
					replacementServerObject[i][key]=jQuery(this).val();
					}
				}else{
					replacementServerObject[i][key]=jQuery(this).val();
				}
			}else{
				if(key == "databaseAddr" && jQuery(this).closest("tr").find(".weld-server-databaseServersDifferent:checked").attr("value") == "false"){
					jQuery(this).attr('value',jQuery(this).closest("li").find('.weld-serverAddr').attr('value'));
				}

				subKeyR = subIndexRG.exec(jQuery(this).attr('name'));
				subKey = subKeyR[1];

				replacementServerObject[i][key][subKey]=jQuery(this).val();
			}
		});
		i=-1;
	});

	jQuery("#weld_server_array").attr("value",JSON.stringify(replacementServerObject));
	

} // end sqsm_scanFieldValues






function weldClone(source) {
      var result = source, i, len;
    if (!source
        || source instanceof Number
        || source instanceof String
        || source instanceof Boolean) {
        return result;
    } else if (Object.prototype.toString.call(source).slice(8,-1) === 'Array') {
        result = [];
        var resultLen = 0;
        for (i = 0, len = source.length; i < len; i++) {
            result[resultLen++] = weldClone(source[i]);
        }
    } else if (typeof source == 'object') {
        result = {};
        for (i in source) {
            if (source.hasOwnProperty(i)) {
                result[i] = weldClone(source[i]);
            }
        }
    }
    return result;
    }; // end weldClone





});

