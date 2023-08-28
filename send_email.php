 <?php
 $domain_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
  header("AMP-Access-Control-Allow-Source-Origin: ".$domain_url);
  header("Access-Control-Expose-Headers: AMP-Access-Control-Allow-Source-Origin");
//header("Access-Control-Allow-Origin: *");
if( isset($_POST['first_user']))
{
$name = $_POST['first_user'];
$phone = $_POST['phone_user'];
$message = $_POST['message_user'];
$adress = $_POST['adress_user'];
$email = $_POST['email_user'];
//$file_user = $_POST['file_user'];

$to = "hurera@geeksroot.com";
$subject = "Apply Form";

$message = '<html><body>';
$message .= '<div style="text-align:center;"><img style="margin-bottom: 20px;" src="http://medicostx.accunity.com/media/logo/stores/1/logo.jpg"></div>';
$message .= '<table border="1" rules="all" style="border: 1px solid black; border-color: #666;margin:0px auto;width: 65%;" cellpadding="10">';
$message .= "<tr><td><strong>First Name</strong> </td><td>" . $name . "</td></tr>";
$message .= "<tr><td><strong>Phone</strong> </td><td>" . $phone . "</td></tr>";
$message .= "<tr><td><strong>Message</strong> </td><td>" . $message . "</td></tr>";
$message .= "<tr><td><strong>Adress</strong> </td><td>" . $adress . "</td></tr>"; 
$message .= "<tr><td><strong>Email</strong> </td><td>" . $email . "</td></tr>";
$message .= "</table>";
$message .= "</body></html>"; 

	    /*//-->MUST BE 'https://';*/
        header("Content-type: application/json");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Origin: *.ampproject.org");

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <noreply@zifanhotels.com>' . "\r\n";

if(mail($to,$subject,$message,$headers)){
	$res[] = array(
				'status' => 'success'
				);
	echo json_encode($res);
				
}
else{
	$res[] = array(
				'status' => 'failed'
				);
	echo json_encode($res);
				
}

$to = $email;
$subject = "Your reservation request has been received";

$message = '<html><body>';
$message .='<p>Thank you for submitting your reservation request. One of our representatives will get in touch with you shortly.</p>';
$message .='<p>Regards, <br> Team Zifan</p>';
$message .= '<div style="text-align:left;"><img style="width: 95px;""margin-top: 4px;""margin-bottom: 20px;" src="www.zifanhotels.com/images/first-logo.png"></div>';
$message .= '</body></html>'; 
	    /*//-->MUST BE 'https://';*/
        header("Content-type: application/json");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Origin: *.ampproject.org");

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <noreply@zifanhotels.com>' . "\r\n";

mail($to,$subject,$message,$headers);

}

 ?>