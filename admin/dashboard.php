<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <?php wp_head(); ?>
</head>

<body>

  <br>

  <div class="row">
    <div class="col-1"></div>
    <div class="col-10">

      <table class="table">
        <thead>
          <tr>
            <th scope="col">Record ID</th>
            <th scope="col">User ID</th>
            <th scope="col">IP Address</th>
            <th scope="col">Date</th>
            <th scope="col">Warning Level</th>
            <th scope="col">Event Type</th>
            <th scope="col">Message</th>
          </tr>
        </thead>
        <tbody>
           <?php 
           global $wpdb;
           $table = $wpdb->prefix . 'event_db';
           $results = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC");
           foreach($results as $res){ ?>
                  <tr>
                  <th scope="row"><?php echo $res->id; ?></th>
                  <td><?php echo $res->userid; ?></td>
                  <td><?php echo $res->ip_address; ?></td>
                  <td><?php echo $res->event_time; ?></td>
                  <td><?php if($res->warning_level == 'low'){
                    echo '<span class="badge rounded-pill text-bg-success">'.$res->warning_level.'</span>';
                  }else if($res->warning_level == 'medium'){
                      echo '<span class="badge rounded-pill text-bg-warning">'.$res->warning_level.'</span>';
                  }else if($res->warning_level == 'high'){
                      echo '<span class="badge rounded-pill text-bg-danger">'.$res->warning_level.'</span>';
                  } ?></td>
                  <td><?php echo $res->event_type; ?></td>
                  <td><?php echo $res->message; ?></td>
                </tr>
                      <?php } ?>
          

        </tbody>
      </table>

    </div>
    <div class="col-1"></div>
  </div>

  <?php wp_footer(); ?>
</body>

</html>