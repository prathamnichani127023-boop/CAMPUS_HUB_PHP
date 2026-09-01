<?php
session_start();
session_unset();
session_destroy();
header('Location: /university_portal/index.php?msg=Logged+out+successfully');
exit();
