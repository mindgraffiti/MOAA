<h3>Create a new import</h3>

<?php 
	echo form_open( $form_action ); 
	echo form_hidden( "datagrab_step", "index" ); 
?>

<p>
	<select name="type">
<?php foreach( $types as $type => $type_label ): ?>
		<option value="<?php echo $type; ?>"><?php echo $type_label ?></option>
<?php endforeach; ?>
	</select>

	<input type="submit" value="Create new import" class="submit" />
</p>

<?php echo form_close(); ?>

<p><br/></p>

<?php if ( count( $saved_imports ) ): ?>

<h3>Use a saved import</h3>

<?php echo form_open( $form_action ); ?>

<?php

$this->table->set_template($cp_table_template);
$this->table->set_heading('ID', 'Name', 'Description', 'Configure', 'Run', 'Delete');

echo $this->table->generate($saved_imports);

echo '<p class="info"><strong>Saved imports</strong> can be run from outside the Control Panel (eg, using a cron job), by visiting the the URL:<br/>' . $action_url  . '<em>id</em><br/>replacing <em>id</em> with the ID of the saved import.</p>';

?>

<?php echo form_close(); ?>

<?php endif; ?>
