<!DOCTYPE html>
<html>
<head>
    <title>Test AJAX</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Test AJAX Request</h1>
    
    <div>
        <label for="note-id">Note ID:</label>
        <input type="number" id="note-id" value="1">
        <button id="test-button">Test API Call</button>
    </div>
    
    <div>
        <h3>Raw Response:</h3>
        <pre id="raw-output" style="background-color: #f0f0f0; padding: 10px; max-height: 200px; overflow: auto;"></pre>
        
        <h3>Parsed Response:</h3>
        <pre id="parsed-output" style="background-color: #e0e0e0; padding: 10px; max-height: 200px; overflow: auto;"></pre>
    </div>
    
    <script>
        $(document).ready(function() {
            $('#test-button').click(function() {
                const noteId = $('#note-id').val();
                
                // Display status
                $('#raw-output').text('Loading...');
                $('#parsed-output').text('');
                
                // Raw XMLHttpRequest to get exact response
                const xhr = new XMLHttpRequest();
                xhr.open('GET', `get_note.php?note_id=${noteId}`, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        const rawResponse = xhr.responseText;
                        $('#raw-output').text(rawResponse);
                        
                        try {
                            const parsedResponse = JSON.parse(rawResponse);
                            $('#parsed-output').text(JSON.stringify(parsedResponse, null, 2));
                        } catch(e) {
                            $('#parsed-output').text('Failed to parse JSON: ' + e.message);
                        }
                    }
                };
                xhr.send();
            });
        });
    </script>
</body>
</html> 