<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login.php");
    exit();
}

require 'config.php';

$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - MEOWIÉ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #000000;
            --hover-color: rgb(249, 199, 249);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Font2", -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        @font-face {
            font-family: "Font";
            src: url("aespa.ttf") format("truetype");
        }

        @font-face {
            font-family: "Font2";
            src: url("HelveticaLTNarrowBold.otf") format("opentype");
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url(background_profile2.jpg);
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            z-index: -1;
        }

        h2 {
            color: black;
            font-family: "Font", Arial, Helvetica, sans-serif;
            font-size: 40px;
        }

        p {
            color: black;
        }
        .profile-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: none;
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        .avatar-container:hover .avatar-upload {
            opacity: 1;
        }

        .avatar-container:hover .avatar {
            filter: brightness(70%);
        }

        .avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            transition: filter 0.3s ease;
        }

        .avatar-upload {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 50%;
        }

        .avatar-upload i {
            font-size: 24px;
            color: white;
        }

        .avatar-upload input {
            display: none;
        }

        .profile-form {
            display: grid;
            gap: 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 500;
            color: black;
        }

        .form-group input,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .save-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 20px;
        }

        .save-button:hover {
            color: black;
            background: white;
        }

        .changepassword-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 20px;
        }

        .changepassword:hover {
            color: black;
            background: white;
        }

        .success-message,
        .error-message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success-message {
            background: #e6ffe6;
            color: #4CAF50;
            border: 1px solid #ccffcc;
        }

        .error-message {
            background: #ffe6e6;
            color: #ff4444;
            border: 1px solid #ffcccc;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
            transition: background-color 0.3s;
        }

        .back-button:hover {
            color: black;
            background: white;
        }

        /* Cropper Modal Styles */
        .crop-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .crop-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 90%;
            max-height: 90vh;
            overflow: auto;
        }

        .crop-area {
            max-width: 500px;
            margin: 0 auto;
        }

        .crop-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 20px auto;
        }

        .crop-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .crop-button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .crop-save {
            background: var(--primary-color);
            color: white;
        }

        .crop-cancel {
            background: #ddd;
        }

        #cropImage {
            max-width: 100%;
            max-height: 60vh;
        }
    </style>
</head>

<body>
    <div class="profile-container">
        <a href="dashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <?php
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?php
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="avatar-container">
                <img src="<?php echo htmlspecialchars($user['avatar'] ? 'uploads/avatars/' . $user['avatar'] : 'uploads/avatars/default_avatar.png'); ?>"
                    alt="Profile Avatar" class="avatar" id="avatarPreview">
                <label class="avatar-upload">
                    <input type="file" id="avatarInput" accept="image/*">
                    <i class="fas fa-camera"></i>
                </label>
            </div>
            <h2><?php echo htmlspecialchars($user['username']); ?></h2>
            <p><?php echo htmlspecialchars($user['email']); ?></p>
        </div>

        <form class="profile-form" id="profileForm">
            <div class="form-group">
                <label for="fullName">Full Name</label>
                <input type="text" id="fullName" name="full_name"
                    value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="bio">Bio</label>
                <textarea id="bio" name="bio"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="birthDate">Birth Date</label>
                <input type="date" id="birthDate" name="birth_date"
                    value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>">
            </div>

            <button type="submit" class="save-button">Save Changes</button>
        </form>
    </div>

    <!-- Crop Modal -->
    <div class="crop-modal" id="cropModal">
        <div class="crop-container">
            <div class="crop-area">
                <img id="cropImage" src="">
            </div>
            <div class="crop-preview"></div>
            <div class="crop-buttons">
                <button class="crop-button crop-save" id="saveCrop">Save</button>
                <button class="crop-button crop-cancel" id="cancelCrop">Cancel</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
        let cropper;

        $(document).ready(function () {
            $('#avatarInput').change(function (e) {
                const file = this.files[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) { // 5MB limit
                        alert('File size too large. Please choose an image under 5MB.');
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function (e) {
                        // Show crop modal
                        $('#cropModal').css('display', 'flex');
                        $('#cropImage').attr('src', e.target.result);

                        // Initialize cropper
                        if (cropper) {
                            cropper.destroy();
                        }

                        cropper = new Cropper($('#cropImage')[0], {
                            aspectRatio: 1,
                            viewMode: 1,
                            preview: '.crop-preview',
                            dragMode: 'move',
                            autoCropArea: 1,
                            responsive: true,
                            restore: false,
                            guides: true,
                            center: true,
                            highlight: false,
                            cropBoxMovable: false,
                            cropBoxResizable: false,
                            toggleDragModeOnDblclick: false
                        });
                    }
                    reader.readAsDataURL(file);
                }
            });

            // Save cropped image
            $('#saveCrop').click(function () {
                const canvas = cropper.getCroppedCanvas({
                    width: 300,
                    height: 300
                });

                canvas.toBlob(function (blob) {
                    const formData = new FormData();
                    formData.append('avatar', blob, 'avatar.jpg');

                    $.ajax({
                        url: 'update_avatar.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            console.log('Raw response:', response);
                            try {
                                const data = typeof response === 'string' ? JSON.parse(response) : response;
                                console.log('Parsed data:', data);

                                if (data.success) {
                                    // Update avatar preview
                                    $('#avatarPreview').attr('src', 'uploads/avatars/' + data.filename + '?t=' + new Date().getTime());
                                    $('#cropModal').hide();
                                    cropper.destroy();
                                    alert('Avatar updated successfully!');
                                } else {
                                    console.error('Upload failed:', data.error);
                                    alert(data.error || 'Error uploading avatar');
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                console.error('Raw response:', response);
                                alert('Error uploading avatar. Please try again.');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Upload error details:');
                            console.error('Status:', status);
                            console.error('Error:', error);
                            console.error('Response:', xhr.responseText);
                            alert('Error uploading avatar. Please try again. Error: ' + error);
                        }
                    });
                }, 'image/jpeg', 0.9);
            });

            // Cancel crop
            $('#cancelCrop').click(function () {
                $('#cropModal').hide();
                if (cropper) {
                    cropper.destroy();
                }
            });

            // Close modal on outside click
            $('#cropModal').click(function (e) {
                if (e.target === this) {
                    $(this).hide();
                    if (cropper) {
                        cropper.destroy();
                    }
                }
            });

            // Profile form submission
            $('#profileForm').submit(function (e) {
                e.preventDefault();
                const formData = $(this).serialize();

                $.ajax({
                    url: 'update_profile.php',
                    type: 'POST',
                    data: formData,
                    success: function (response) {
                        try {
                            const data = JSON.parse(response);
                            if (data.success) {
                                alert('Profile updated successfully!');
                            } else {
                                alert(data.error || 'Error updating profile');
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('Error updating profile. Please try again.');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Update error:', error);
                        alert('Error updating profile. Please try again.');
                    }
                });
            });
        });
    </script>
</body>

</html>