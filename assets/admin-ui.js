jQuery(document).ready(function ($) {
  $("#post_type").on("change", function () {
    const postType = $(this).val();
    const container = $("#meta-suggestions");
    container.html("<em>Loading...</em>");

    $.post(
      HeadlessAPIData.ajax_url,
      {
        action: "headless_api_get_meta_fields",
        post_type: postType,
        _ajax_nonce: HeadlessAPIData.nonce,
      },
      function (response) {
        if (response.success) {
          const fields = response.data;
          if (fields.length === 0) {
            container.html("<em>No meta fields found.</em>");
            return;
          }

          let html = "";
          fields.forEach((field) => {
            html += '<div style="margin-bottom:5px;">';
            html += `<label><input type="checkbox" class="field-selector" data-field="${field}"> ${field}</label>`;
            html += ` → <input type="text" name="field_map[${field}]" placeholder="Alias (optional)">`;
            html += "</div>";
          });

          container.html(html);
        } else {
          container.html("<em>Error: " + response.data + "</em>");
        }
      }
    );
  });

  $(document).on("change", ".field-selector", function () {
    const isChecked = $(this).is(":checked");
    const field = $(this).data("field");
    const input = $(`input[name="field_map[${field}]"]`);
    if (isChecked) {
      input.attr("name", "fields_map[" + field + "]");
    } else {
      input.attr("name", "field_map[" + field + "]");
    }
  });
});
