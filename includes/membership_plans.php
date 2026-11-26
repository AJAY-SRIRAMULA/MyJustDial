<?php
function mpOptions(PDO $pdo, string $sql): array {
    return $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
}
function mpMeta(PDO $pdo, int $id): array {
    $q=$pdo->prepare('SELECT `key`,value FROM users_meta WHERE `database`=? AND database_id=? ORDER BY meta_id');
    $q->execute(['subscription_types',$id]); return $q->fetchAll(PDO::FETCH_KEY_PAIR);
}
function mpPutMeta(PDO $pdo,int $id,string $key,string $value): void {
    $q=$pdo->prepare('DELETE FROM users_meta WHERE `database`=? AND database_id=? AND `key`=?'); $q->execute(['subscription_types',$id,$key]);
    $q=$pdo->prepare('INSERT INTO users_meta (`database`,database_id,`key`,value,date_added) VALUES (?,?,?,?,?)'); $q->execute(['subscription_types',$id,$key,$value,date('YmdHis')]);
}
function mpIds($value): array { return array_values(array_unique(array_filter(array_map('intval',explode(',',(string)$value))))); }
function mpMap($value): array { $a=json_decode((string)$value,true); return is_array($a)?$a:[]; }
function mpFields(array $options): array {
    $yes=['0'=>'No','1'=>'Yes']; $pos=['0'=>'Hide','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6'];
    $f=[];
    $add=function($tab,$section,$key,$label,$type='text',$choices=[],$default='',$meta=false) use (&$f){$f[$tab][$section][]=compact('key','label','type','choices','default','meta');};
    $add(0,'Set Pricing Options','profile_type','Account Type','select',['free'=>'Free','paid'=>'Paid'],'paid');
    $add(0,'Set Pricing Options','signup_form','Sign Up Form','select',$options['forms'],'',true);
    $add(0,'Set Pricing Options','payment_default','Preferred Payment Term','select',['monthly'=>'Monthly','quarterly'=>'Quarterly','semiyearly'=>'Semi-annually','yearly'=>'Annually','biennially'=>'Biennially','triennially'=>'Triennially'],'yearly');
    foreach(['initial_amount'=>'Initial Setup Fee','monthly_amount'=>'Monthly Price','quarterly_amount'=>'Quarterly Price','semiyearly_amount'=>'Semi-annual Price','yearly_amount'=>'Annual Price','biennially_amount'=>'Biennial Price','triennially_amount'=>'Triennial Price'] as $k=>$v) {$add(0,'Set Pricing Options',$k,$v,'money',[],'0',!in_array($k,['initial_amount','monthly_amount','yearly_amount']));$add(0,'Set Pricing Options','hide_'.$k,'Hide '.$v,'select',$yes,'0',true);}
    $add(0,'Set Pricing Options','lead_price','Price to Purchase Leads','money',[],'0');
    $add(0,'Set Pricing Options','conversion_tracking_code','Successful Sign Up / Upgrade Conversion Tracking Code Widget','select',$options['widgets'],'',true);
    foreach(['disallow_signups'=>'Disable Public Sign Ups','signup_email_admin'=>'Send Copy to Default Website Email','enable_credits'=>'Display Available Credits','enable_credits_referrals'=>'Assign Referral Credits','referral_credits_only'=>'Only Assign Credits When Referred'] as $k=>$v) $add(0,'Sign Up Settings',$k,$v,'select',$yes,'0',true);
    $add(0,'Sign Up Settings','signup_email_template','Sign Up Email Sent','select',$options['emails']);
    $add(0,'Sign Up Settings','auto_activate','Account Status After Sign Up','select',['active'=>'Active','pending'=>'Pending','inactive'=>'Inactive'],'active',true);
    $add(0,'Sign Up Settings','signup_sidebar','Sign Up Page Sidebar','select',$options['sidebars']);
    $add(0,'Sign Up Settings','signup_sidebar_position','Sidebar Position','select',['left'=>'Left','right'=>'Right'],'right',true);
    $add(0,'Sign Up Settings','signup_profession','Pre-select Top Category','select',$options['categories'],'',true);
    $add(0,'Sign Up Settings','listing_type','Pre-select Listing Type','select',[''=>'None','Individual'=>'Individual','Company'=>'Company'],'',true);
    $add(0,'Sign Up Settings','signup_redirect','Redirect After Sign Up','text',[],'/account/home',true);
    $add(0,'Sign Up Settings','login_redirect','Redirect After Login','text',[],'/account/home');
    $add(0,'Sign Up Settings','custom_checkout_url','Member Sign Up URL','text',[],'',true);
    foreach(['enable_credits_signup'=>'Reward Credits After Sign Up','enable_credits_upgrade'=>'Reward Credits After Plan Change','enable_credits_referrals_amount'=>'Referral Credits Amount'] as $k=>$v) $add(0,'Sign Up Settings',$k,$v,'money',[],'0',true);
    $add(0,'Change Plan / Upgrade / Downgrade','upgrade_email_template','Upgrade Email Sent','select',$options['emails']);
    $add(0,'Change Plan / Upgrade / Downgrade','upgrade_email_admin','Send Copy to Default Website Email','select',$yes,'0',true);
    $add(0,'Change Plan / Upgrade / Downgrade','status_after_upgrade','Account Status After Upgrade','select',['keep'=>'Keep Current','active'=>'Active','pending'=>'Pending','inactive'=>'Inactive'],'keep',true);
    $add(0,'Change Plan / Upgrade / Downgrade','upgrade_page_header','Upgrade Page Header','text',[],'',true);$add(0,'Change Plan / Upgrade / Downgrade','upgrade_page_description','Upgrade Page Description','textarea',[],'',true);
    foreach(['location_map_order'=>'Location Map','user_quote_order'=>'Member Quote','contact_details_order'=>'Contact Details','listing_details_order'=>'Listing Details','about_order'=>'About Member','list_services_areas_order'=>'Service Areas'] as $k=>$v) $add(1,'Profile Overview Section Positions',$k,$v,'select',$pos,'0',true);
    foreach(['website_link'=>'Show Website Link','social_link'=>'Show Social Media Links','show_phone'=>'Show Phone Number','hide_specialties'=>'Hide Specialties Tab','display_ads'=>'Display Banner Ads','receive_messages'=>'Receive Messages'] as $k=>$v) $add(1,'Profile Page Settings',$k,$v,'select',$yes,'1');
    foreach(['show_street_address'=>'Show Street Address','enable_click_Call'=>'Enable Click to Call','show_breadcrumbs'=>'Show Breadcrumbs','enable_receiving_chat_messages'=>'Receive Private Chats','enable_sending_chat_messages'=>'Send Private Chats','hide_profile_header'=>'Hide Header','hide_profile_footer'=>'Hide Footer','show_send_message'=>'Show Send Message Button'] as $k=>$v) $add(1,'Profile Page Settings',$k,$v,'select',$yes,'0',true);
    $add(1,'Profile Page Settings','profile_sidebar','Profile Sidebar','select',$options['sidebars']);$add(1,'Profile Page Settings','profile_connection_banner_position','Connection / Claim Banner Position','select',['below_profile_photo'=>'Below Profile Photo','above_profile_photo'=>'Above Profile Photo','hidden'=>'Hidden'],'below_profile_photo',true);$add(1,'Profile Page Settings','message_form','Send Message Form','select',$options['forms'],'',true);
    foreach(['reviews_enabled'=>'Enable Member Reviews','admin_accept_delete_reviews'=>'Require Admin Approval','hide_reviews_rating_options'=>'Hide Star Rating','accept_delete_reviews'=>'Allow Accept / Delete','reply_visitor_review'=>'Allow Replies','allow_review_images'=>'Allow Image Uploads'] as $k=>$v) $add(1,'Member Review Settings',$k,$v,'select',$yes,'0',true);
    foreach(['profile_badge'=>'Profile Badge (50 × 50)','category_badge'=>'Search Results Badge (50 × 50)','dashboard_badge'=>'Dashboard Badge (250 × 250)'] as $k=>$v) $add(1,'Membership Badges',$k,$v,'image',[],'',$k==='dashboard_badge');
    foreach(['show_contact_detail'=>'Enable Contact Details','contact_required'=>'Require Contact Details','show_listing_details'=>'Enable Additional Details','show_about_tab'=>'Enable About Me','show_profile_photo'=>'Enable Profile Photo Upload','show_logo_upload'=>'Enable Logo Upload','coverPhoto'=>'Enable Cover Photo Upload'] as $k=>$v) $add(2,'Member Profile Forms',$k,$v,'select',$yes,'1',true);
    foreach(['contact_details_form'=>'Contact Details Form','listing_details_form'=>'Additional Details Form','about_form'=>'About Me Form'] as $k=>$v) $add(2,'Member Profile Forms',$k,$v,'select',$options['forms']);
    foreach(['show_dashboard_steps'=>'Profile Completeness Checklist','show_dashboard_progress_bar'=>'Profile Progress Bar','show_add_content'=>'Publish Content Quick Links','show_dashboard_manage_listing'=>'Manage Listing Quick Links','show_dashboard_account_details'=>'Account Details Snapshot','show_dashboard_member_badge'=>'Embeddable Member Badge','show_dashboard_qr_code'=>'QR Code','show_dashboard_transaction_history'=>'Recent Transactions','hide_billing_links'=>'Hide Billing Information','hide_notifications'=>'Hide Manage Leads','display_ads_dashboard'=>'Display Banner Ads','enable_post_feed'=>'Personal Post Feed','show_business_toolkit'=>'Business Toolkit','promote_feature_verify'=>'Verify Listing','social_login'=>'Enable Social Login (requires live SSL domain)'] as $k=>$v) $add(2,'Dashboard Settings',$k,$v,'select',$yes,'0',true);
    $add(2,'Dashboard Settings','menu_name','Logged-in Main Menu','select',$options['menus']);foreach(['user_dashboard_header_widget'=>'Dashboard Header Widget','user_dashboard_footer_widget'=>'Dashboard Footer Widget'] as $k=>$v) $add(2,'Dashboard Settings',$k,$v,'select',$options['widgets'],'',true);
    $add(2,'Dashboard Settings','profile_statistics','Profile Statistics','select',['0'=>'Disabled','1'=>'Enabled'],'0',true);$add(2,'Dashboard Settings','stock_libraries','Stock Image Libraries','multi',['pexel'=>'Pexels','pixabay'=>'Pixabay','giphy'=>'Giphy','unsplash'=>'Unsplash'],'',true);
    foreach(['searchable'=>'Searchable','index_rule'=>'Noindex / Nofollow','nofollow_links'=>'Nofollow Links'] as $k=>$v) $add(3,'Search Visibility',$k,$v,'select',$yes,'0');
    $add(3,'Search Visibility','membership_restriction','Members-only Access','select',['0'=>'Everyone','1'=>'Members Only'],'0',true);$add(3,'Search Visibility','search_priority','Search Priority (0 highest)','number',[],'0');$add(3,'Search Visibility','service_limit','Maximum Sub-level Categories','text',[],'10');$add(3,'Search Visibility','subscription_filename','Plan Search Results URL','text');
    $add(3,'Service Areas','location_settings','Service Area Types','multi',array_combine(['Country','State','County','City','Zip'],['Country','State','County','City','Zip']));$add(3,'Service Areas','location_limit','Maximum Locations','text',[],'0');
    foreach(['_member_results_highlight'=>['Highlight Search Results','select',$yes,'0'],'_highlight_border_color'=>['Border Color','color',[],'#e6b400'],'_highlight_text_color'=>['Text Color','color',[],'#222222'],'_highlight_border_width'=>['Border Width','number',[],'2'],'_highlight_border_radius'=>['Border Radius','number',[],'4'],'_highlight_text_message'=>['Text Message','text',[],'Featured'],'_highlight_font_size'=>['Font Size','number',[],'14'],'_highlight_text_position'=>['Text Position','select',['top'=>'Top','bottom'=>'Bottom'],'top']] as $k=>$v) $add(3,'Search Results Highlight',$k,$v[0],$v[1],$v[2],$v[3],true);
    foreach(['fav_button'=>'Bookmark Favorites','member_specialties_active'=>'Member Specialties','hide_parent_accounts'=>'Hide Parent Accounts','show_subaccounts_tab'=>'Show Sub Accounts Tab'] as $k=>$v) $add(4,'Publishing Settings',$k,$v,'select',$yes,'0',true);
    $add(4,'Publishing Settings','sub_accounts_active','Activate Sub Accounts','select',$yes,'0',true);$add(4,'Publishing Settings','sub_accounts_limit','Maximum Sub Accounts','number',[],'0',true);
    return $f;
}
