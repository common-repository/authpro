// Check for protection code and add AuthPro sid to comments form

var acc=document.currentScript.getAttribute('data-acc');

var apfu='/'; if (acc!=null) { apfu='https://www.authpro.com/auth/'+acc+'/?action=ppfail'; } 
if ((typeof(auth_res)=='undefined')||(auth_res!='ok')) { document.location.href=apfu; }

function ap_comments_sid() {
  if((typeof(usid)!="undefined")&&(document.getElementById('commentform')!=null)) {
    var ap_sid=document.createElement('input');
    ap_sid.setAttribute('type','hidden');
    ap_sid.setAttribute('name','ap_sid');
    ap_sid.setAttribute('value',usid);
    document.getElementById('commentform').appendChild(ap_sid);
  }
}

document.addEventListener("DOMContentLoaded", ap_comments_sid);
