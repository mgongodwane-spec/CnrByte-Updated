// Handle recipient search
document.getElementById("searchRecipient").addEventListener("click", function() {
  const type = document.getElementById("recipientType").value;
  const query = document.getElementById("recipientSearch").value;
  const resultsDiv = document.getElementById("searchResults");
  const previewDiv = document.getElementById("selectedPreview");

  fetch("/searchRecipient", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ type: type, query: query })
  })
  .then(response => response.json())
  .then(data => {
    resultsDiv.innerHTML = ""; // clear old results
    previewDiv.innerHTML = ""; // clear old preview

    if (data.length > 0) {
      data.forEach((recipient, index) => {
        const option = document.createElement("div");
        option.className = "result-item";
        option.innerHTML = `
          <label>
            <input type="radio" name="recipientChoice" value="${recipient.id}" ${index === 0 ? "checked" : ""}>
            ${recipient.name} (ID: ${recipient.id})
          </label>
        `;
        resultsDiv.appendChild(option);
      });

      // Show initial preview
      const firstRecipient = data[0];
      previewDiv.innerText = `Currently selected: ${firstRecipient.name} (ID: ${firstRecipient.id})`;

      // Add change handler for radio buttons
      document.querySelectorAll("input[name='recipientChoice']").forEach(radio => {
        radio.addEventListener("change", function() {
          const selectedRadio = document.querySelector("input[name='recipientChoice']:checked");
          if (selectedRadio) {
            const selectedLabel = selectedRadio.parentElement.textContent.trim();
            previewDiv.innerText = `Currently selected: ${selectedLabel}`;
          }
        });
      });
    } else {
      resultsDiv.innerText = "No match found.";
    }
  })
  .catch(err => {
    resultsDiv.innerText = "Error: " + err.message;
  });
});

// Handle deposit form submission
document.getElementById("depositForm").addEventListener("submit", function(event) {
  event.preventDefault();
  const parcelId = document.getElementById("parcelId").value;
  const senderId = document.getElementById("senderId").value;

  const selectedRadio = document.querySelector("input[name='recipientChoice']:checked");
  const recipientId = selectedRadio ? selectedRadio.value : "";

  if (!recipientId) {
    document.getElementById("response").innerText =
      "Please search and select a recipient first.";
    return;
  }

  fetch("/deposit", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      parcel_id: parcelId,
      sender_id: senderId,
      receiver_id: recipientId
    })
  })
  .then(response => response.json())
  .then(data => {
    document.getElementById("response").innerText =
      `Locker assigned: ${data.locker_id}, Pickup code: ${data.pickup_code}`;
  })
  .catch(err => {
    document.getElementById("response").innerText = "Error: " + err.message;
  });
});