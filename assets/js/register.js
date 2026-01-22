$(function () {
  $(function () {
  var token = localStorage.getItem("authToken");
  if (token) {
    window.location.href = "profile.html";
    return;
  }

  // existing register AJAX code...
});
  function showMsg(ok, text) {
    $("#msg")
      .removeClass("d-none alert-success alert-danger")
      .addClass(ok ? "alert-success" : "alert-danger")
      .text(text);
  }

  $("#btnRegister").on("click", function () {
    $.ajax({
      url: "assets/php/register.php",
      type: "POST",
      dataType: "json",
      data: {
        email: $("#email").val(),
        password: $("#password").val()
      },
      success: function (res) {
        if (res.success) {
          showMsg(true, res.message);
          setTimeout(function () {
            window.location.href = "login.html";
          }, 700);
        } else {
          showMsg(false, res.message);
        }
      },
      error: function () {
        showMsg(false, "Register failed");
      }
    });
  });
});