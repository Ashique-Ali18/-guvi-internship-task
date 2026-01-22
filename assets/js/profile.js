$(function () {
  var token = localStorage.getItem("authToken");
  if (!token) {
    window.location.href = "login.html";
    return;
  }

  function showMsg(ok, text) {
    $("#msg")
      .removeClass("d-none alert-success alert-danger")
      .addClass(ok ? "alert-success" : "alert-danger")
      .text(text);
  }

  function handleAuthFailure(message) {
    if (message === "Invalid/expired session" || message === "Missing token") {
      localStorage.removeItem("authToken");
      window.location.href = "login.html";
      return true;
    }
    return false;
  }

  function fetchProfile() {
    $.ajax({
      url: "assets/php/profile.php",
      type: "POST",
      dataType: "json",
      headers: { "Authorization": "Bearer " + token },
      data: { action: "fetch" },
      success: function (res) {
        if (!res.success) {
          showMsg(false, res.message);
          handleAuthFailure(res.message);
          return;
        }

        $("#age").val(res.data.age || "");
        $("#dob").val(res.data.dob || "");
        $("#contact").val(res.data.contact || "");
      },
      error: function () {
        showMsg(false, "Fetch failed");
      }
    });
  }

  $("#btnSave").on("click", function () {
    $.ajax({
      url: "assets/php/profile.php",
      type: "POST",
      dataType: "json",
      headers: { "Authorization": "Bearer " + token },
      data: {
        action: "update",
        age: $("#age").val(),
        dob: $("#dob").val(),
        contact: $("#contact").val()
      },
      success: function (res) {
        if (!res.success) {
          showMsg(false, res.message);
          handleAuthFailure(res.message);
          return;
        }
        showMsg(true, res.message);
      },
      error: function () {
        showMsg(false, "Update failed");
      }
    });
  });

  $("#btnLogout").on("click", function () {
    $.ajax({
      url: "assets/php/profile.php",
      type: "POST",
      dataType: "json",
      headers: { "Authorization": "Bearer " + token },
      data: { action: "logout" },
      complete: function () {
        localStorage.removeItem("authToken");
        window.location.href = "login.html";
      }
    });
  });

  fetchProfile();
});