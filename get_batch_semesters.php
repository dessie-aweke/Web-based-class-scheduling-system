```php
   <?php
   header('Content-Type: application/json');
   include 'database/db_connection.php';

   if (!isset($_GET['batch_id'])) {
       echo json_encode(['error' => 'No batch ID provided']);
       exit;
   }

   $batch_id = $conn->real_escape_string($_GET['batch_id']);
   $query = "SELECT DISTINCT s.semester_id, s.semester_name
             FROM semesters s
             JOIN batch_course_assignments bca ON s.semester_id = bca.semester_id
             WHERE bca.batch_id = '$batch_id'
             ORDER BY s.semester_name";
   $result = $conn->query($query);

   if ($result === false) {
       echo json_encode(['error' => 'Query failed: ' . $conn->error]);
       exit;
   }

   $semesters = [];
   while ($row = $result->fetch_assoc()) {
       $semesters[] = [
           'semester_id' => $row['semester_id'],
           'semester_name' => $row['semester_name'] ?: 'Unknown'
       ];
   }

   echo json_encode(['semesters' => $semesters]);
   $conn->close();
   ?>
   ```