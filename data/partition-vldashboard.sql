DROP PROCEDURE IF EXISTS create_new_partitions;

DELIMITER $$
CREATE PROCEDURE create_new_partitions(p_schema varchar(64), 
                                        p_table varchar(64), 
                                        p_months_to_add int)
   LANGUAGE SQL
   NOT DETERMINISTIC
   SQL SECURITY INVOKER
BEGIN  
   DECLARE done INT DEFAULT FALSE;
   DECLARE current_partition_name varchar(64);
   DECLARE current_partition_ts int;
   
   -- We'll use this cursor later to check
   -- whether a particular already exists.
   -- @partition_name_to_add will be
   -- set later.
   DECLARE cur1 CURSOR FOR 
   SELECT partition_name 
   FROM information_schema.partitions 
   WHERE TABLE_SCHEMA = p_schema 
   AND TABLE_NAME = p_table 
   AND PARTITION_NAME != 'p_first'
   AND PARTITION_NAME != 'p_future'
   AND PARTITION_NAME = @partition_name_to_add;
   
   -- We'll also use this cursor later 
   -- to query our temporary table.
   DECLARE cur2 CURSOR FOR 
   SELECT partition_name, partition_range_ts 
   FROM partitions_to_add;
   
   DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
   
   DROP TEMPORARY TABLE IF EXISTS partitions_to_add;
   
   CREATE TEMPORARY TABLE partitions_to_add (
      partition_name varchar(64),
      partition_range_ts int
   );
   
   SET @partitions_added = FALSE;
   SET @months_ahead = 0;
   
   -- Let's go through a loop and add each month individually between
   -- the current month and the month p_months_to_add in the future.
   WHILE @months_ahead <= p_months_to_add DO
      -- We figure out what the correct month is by adding the
      -- number of months to the current date
      SET @date = CURDATE();
      SET @q = 'SELECT DATE_ADD(?, INTERVAL ? MONTH) INTO @month_to_add';
      PREPARE st FROM @q;
      EXECUTE st USING @date, @months_ahead;
      DEALLOCATE PREPARE st;
      SET @months_ahead = @months_ahead + 1;
      
      -- Then we format the month in the same format used
      -- in our partition names.
      SET @q = 'SELECT DATE_FORMAT(@month_to_add, ''%Y%m'') INTO @formatted_month_to_add';
      PREPARE st FROM @q;
      EXECUTE st;
      DEALLOCATE PREPARE st;
      
      -- And then we use the formatted date to build the name of the
      -- partition that we want to add. This partition name is
      -- assigned to @partition_name_to_add, which is used in
      -- the cursor declared at the start of the procedure.
      SET @q = 'SELECT CONCAT(''p'', @formatted_month_to_add) INTO @partition_name_to_add';
      PREPARE st FROM @q;
      EXECUTE st;
      DEALLOCATE PREPARE st;
     
      SET done = FALSE; 
      SET @first = TRUE;
     
      -- And then we loop through the results returned by the cursor,
      -- and if a row already exists for the current partition, 
      -- then we do not need to create the partition.
      OPEN cur1;

      read_loop: LOOP
         FETCH cur1 INTO current_partition_name;
      
         -- The cursor returned 0 rows, so we can create the partition.
         IF done AND @first THEN
            SELECT CONCAT('Creating partition: ', @partition_name_to_add);

            -- Now we need to get the end date of the new partition.
            -- Note that the date is for the non-inclusive end range,
            -- so we actually need the date of the first day of the *next* month.

            -- First, let's get a date variable for the first of the partition month
            SET @q = 'SELECT DATE_FORMAT(@month_to_add, ''%Y-%m-01 00:00:00'') INTO @month_to_add';
            PREPARE st FROM @q;
            EXECUTE st;
            DEALLOCATE PREPARE st; 

            -- Then, let's add 1 month
            SET @q = 'SELECT DATE_ADD(?, INTERVAL 1 MONTH) INTO @partition_end_date';
            PREPARE st FROM @q;
            EXECUTE st USING @month_to_add;
            DEALLOCATE PREPARE st;

            -- We need the date in UNIX timestamp format.  
            SELECT UNIX_TIMESTAMP(@partition_end_date) INTO @partition_end_ts;
         
            -- Now insert the information into our temporary table
            INSERT INTO partitions_to_add VALUES (@partition_name_to_add, @partition_end_ts);
            SET @partitions_added = TRUE;
         END IF;
        
         -- Since we had at least one row returned, we know the
         -- partition already exists.
         IF ! @first THEN
            LEAVE read_loop;
         END IF;
        
         SET @first = FALSE;
      END LOOP;
     
     CLOSE cur1;
   END WHILE;
   
   -- Let's actually add the partitions now.
   IF @partitions_added THEN
      -- First we need to build the actual ALTER TABLE query.
      SET @schema = p_schema;
      SET @table = p_table;
      SET @q = 'SELECT CONCAT(''ALTER TABLE '', @schema, ''.'', @table, '' REORGANIZE PARTITION p_future INTO ( '') INTO @query';
      PREPARE st FROM @q;
      EXECUTE st;
      DEALLOCATE PREPARE st;
     
      SET done = FALSE;
      SET @first = TRUE;
     
      OPEN cur2;

      read_loop: LOOP
         FETCH cur2 INTO current_partition_name, current_partition_ts;
       
        IF done THEN
            LEAVE read_loop;
         END IF;
      
         -- If it is not the first partition, 
         -- then we need to add a comma
         IF ! @first THEN
            SET @q = 'SELECT CONCAT(@query, '', '') INTO @query';
            PREPARE st FROM @q;
            EXECUTE st;
            DEALLOCATE PREPARE st;
         END IF;

         -- Add the current partition
         SET @partition_name =  current_partition_name;
         SET @partition_ts =  current_partition_ts;         
         SET @q = 'SELECT CONCAT(@query, ''PARTITION '', @partition_name, '' VALUES LESS THAN ('', @partition_ts, '')'') INTO @query';
         PREPARE st FROM @q;
         EXECUTE st;
         DEALLOCATE PREPARE st;
       
         SET @first = FALSE;
      END LOOP;
     
      CLOSE cur2;
     
      -- We also need to include the p_future partition
      SET @q = 'SELECT CONCAT(@query, '', PARTITION p_future VALUES LESS THAN (MAXVALUE))'') INTO @query';
      PREPARE st FROM @q;
      EXECUTE st;
      DEALLOCATE PREPARE st;
     
      -- And then we prepare and execute the ALTER TABLE query.
      PREPARE st FROM @query;
      EXECUTE st;
      DEALLOCATE PREPARE st;  
   END IF;
   
   DROP TEMPORARY TABLE partitions_to_add;
END$$
DELIMITER ;