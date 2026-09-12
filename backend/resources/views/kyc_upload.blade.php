<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KYC Chunk Upload</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .progress { width: 100%; background: #eee; height: 20px; margin-top: 10px; }
        #progressBar { width: 0; height: 100%; background: green; color: white; text-align: center; }
    </style>
</head>
<body>
    <h2>KYC Document Upload</h2>

    <input type="text" id="fullName" placeholder="Full Name"><br><br>

    <select id="country">
        <option value="BW">Botswana</option>
        <option value="ZA">South Africa</option>
    </select><br><br>

    <select id="idType">
        <option value="Omang">Omang</option>
        <option value="Passport">Passport</option>
    </select><br><br>

    <input type="file" id="document"><br><br>

    <button type="button" id="uploadBtn">Upload</button>

    <div class="progress">
        <div id="progressBar"></div>
    </div>

    <div id="status"></div>

        {{-- <h4>Uploaded Files</h4>
    <div class="row" id="uploadedFiles">
        @foreach($fileUrls as $file)
            <div class="col-md-3 mb-3">
                <a href="{!! $file !!}" target="_blank" download>
                    <div class="kt-avatar__holder"
                        style="background-image:url('{!! $file !!}');
                               width:150px; height:150px;
                               background-size:cover; border-radius:8px;
                               box-shadow:0 2px 6px rgba(0,0,0,.2);">
                    </div>
                </a>
            </div>
        @endforeach
    </div> --}}

    <h3>Uploaded Files</h3>
    <div id="uploadedFiles"></div>


    <script>
        function updateProgress(current, total) {
            let percent = Math.floor((current / total) * 100);
            $("#progressBar").css("width", percent + "%").text(percent + "%");
        }

        function uploadFile(file, metadata, onComplete) {
            let chunkSize = 100 * 1024; // 100KB
            let totalChunks = Math.ceil(file.size / chunkSize);
            let currentChunk = 0;

            function sendChunk(retries = 0) {
                let start = currentChunk * chunkSize;
                let end = Math.min(start + chunkSize, file.size);
                let chunk = file.slice(start, end);

                let formData = new FormData();
                formData.append("file", chunk);
                formData.append("fileName", file.name);
                formData.append("chunkIndex", currentChunk);
                formData.append("totalChunks", totalChunks);

                if (currentChunk === totalChunks - 1) {
                    formData.append("full_name", metadata.full_name);
                    formData.append("country", metadata.country);
                    formData.append("id_type", metadata.id_type);
                }

                $.ajax({
                    url: "{{ route('kyc.chunk.upload') }}",
                    type: "POST",
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (res) {
                        if (currentChunk === totalChunks - 1 && res.status === "completed") {
                            if (typeof onComplete === "function") {
                                onComplete(res);
                            }
                        } else {
                            currentChunk++;
                            updateProgress(currentChunk, totalChunks);
                            if (currentChunk < totalChunks) sendChunk();
                        }
                    },
                    error: function () {
                        if (retries < 3) {
                            console.log("Retrying chunk " + currentChunk);
                            sendChunk(retries + 1);
                        } else {
                            alert("Upload failed after retries.");
                        }
                    }
                });
            }

            sendChunk();
        }

        $("#uploadBtn").on("click", function () {
            let file = $("#document")[0].files[0];
            if (!file) {
                alert("Please select a file first.");
                return;
            }

            let metadata = {
                full_name: $("#fullName").val(),
                country: $("#country").val(),
                id_type: $("#idType").val()
            };

            uploadFile(file, metadata, function (response) {
                // $("#status").html("✅ File uploaded successfully!<br>S3 URL: <a href='" + response.s3_url + "' target='_blank'>" + response.s3_url + "</a>");
                $("#status").text("✅ File uploaded successfully!");

    // CloudFront Base URL from helper
    let cloudFrontUrl = @json(\AlphaDirect\Helper::getCloudFrontURL(''));

    // If backend returns `s3_url` like "uploads/abc.png"
    let finalUrl = cloudFrontUrl + response.s3_url + "?v=" + Date.now();

    $("#preview").html(
      "<p><strong>Uploaded File Preview:</strong></p>" +
      "<img src='" + finalUrl + "' alt='Uploaded Document' />"
    );
            });
        });
</script>
<script>
    // CloudFront base URL
    let cloudFrontUrl = @json(\AlphaDirect\Helper::getCloudFrontURL(''));
console.log("test ",cloudFrontUrl);

    // // Fetch files from backend
    // $.get("{{ route('kyc.files.list') }}", function(response) {
    //     if (response.files && response.files.length > 0) {
    //         response.files.forEach(function(filePath) {
    //             // let finalUrl = cloudFrontUrl + filePath + "?v=" + Date.now();
    //             let finalUrl = filePath;
    //             console.log("test ",finalUrl);


    //             $("#uploadedFiles").append(`
    //                 <div class="file-item" style="margin:10px;display:inline-block;text-align:center;">
    //                     <img src="${finalUrl}" width="120" style="border:1px solid #ddd;padding:3px;" />
    //                     <br/>
    //                     <a href="${finalUrl}" target="_blank">View</a>
    //                 </div>
    //             `);
    //         });
    //     } else {
    //         $("#uploadedFiles").html("<p>No files uploaded yet.</p>");
    //     }
    // }).fail(function(xhr) {
    //     console.error("Error fetching files:", xhr.responseText);
    //     $("#uploadedFiles").html("<p>⚠️ Could not load files.</p>");
    // });

    // Fetch files from backend
$.get("{{ route('kyc.files.list') }}", function(response) {
    if (response.files && response.files.length > 0) {
        response.files.forEach(function(file) {
            $("#uploadedFiles").append(`
                <div class="file-item" style="margin:10px;display:inline-block;text-align:center;">
                    <img src="${file.file_url}" width="120" style="border:1px solid #ddd;padding:3px;" />
                    <p><strong>${file.full_name}</strong></p>
                    <p>${file.country} - ${file.id_type}</p>
                    <a href="${file.file_url}" target="_blank">View</a>
                </div>
            `);
        });
    } else {
        $("#uploadedFiles").html("<p>No files uploaded yet.</p>");
    }
}).fail(function(xhr) {
    console.error("Error fetching files:", xhr.responseText);
    $("#uploadedFiles").html("<p>⚠️ Could not load files.</p>");
});

</script>
</body>
</html>
