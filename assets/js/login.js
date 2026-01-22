$(function () {
    var token = localStorage.getItem("authToken");
if (token) {
  window.location.href = "profile.html";
  return;
}
  function showMsg(ok, text) {
    $("#msg")
      .removeClass("d-none alert-success alert-danger")
      .addClass(ok ? "alert-success" : "alert-danger")
      .text(text);
  }

  $("#btnLogin").on("click", function () {
    $.ajax({
      url: "assets/php/login.php",
      type: "POST",
      dataType: "json",
      data: {
        email: $("#email").val(),
        password: $("#password").val()
      },
      success: function (res) {
        if (res.success) {
          localStorage.setItem("authToken", res.data.token);
          window.location.href = "profile.html";
        } else {
          showMsg(false, res.message);
        }
      },
      error: function () {
        showMsg(false, "Login failed");
      }
    });
  });
});