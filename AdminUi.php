<?php

namespace WowMediaLibraryFix;

class AdminUi {
	static public function th( $d ) {
		$label = esc_html( $d['label'] );
		if ( isset( $d['id'] ) ) {
			$label = '<label for="' . esc_attr( $d['id'] ) . '">' .
				$label . '</label>';
		}

		echo '<th scope="row">' . $label . '</th>';
	}



	static public function fieldset_start( $label ) {
		echo '<fieldset><legend class="screen-reader-text">';
		echo '<span>' . esc_html( $label ) . '</span></legend>';
	}



	static public function fieldset_end() {
		//echo '<p class="description"></p>';
		echo '</fieldset>';
	}



	static public function description( $message ) {
		echo '<p class="description">' . $message . '</p>';
	}



	static public function checkbox( $d ) {
		echo '<label for="' . esc_attr( $d['id'] ) . '">';
		echo '<input name="' . esc_attr( $d['id'] ) .
			'" type="hidden" value="__checkbox_0" />';
		echo '<input name="' . esc_attr( $d['id'] ) .
			'" type="checkbox" id="' . esc_attr( $d['id'] ) .
			'" value="__checkbox_1" ' .
			checked( $d['value'], true, false ) . '> ';
		echo esc_html( $d['name'] );
		echo '</label>';

		if ( isset( $d['description'] ) ) {
			AdminUi::description( $d['description'] );
		}
	}



	static public function selectbox( $d ) {
		echo '<select name="' . esc_attr( $d['id'] ) . '" id="' .
			esc_attr( $d['id'] ) . '">';

		foreach ( $d['values'] as $i ) {
			if ( !is_array( $i ) ) {
				echo '<option ' . selected( $d['value'], $i ) . '>';
				echo esc_html( $i );
				echo '</option>';
			} else {
				echo '<option value="' . esc_attr( $i['value'] ) . '"' .
					selected( $d['value'], $i['value'] ) . '>';
				echo esc_html( $i['name'] );
				echo '</option>';
			}
		}

		echo '</select>';
	}



	static public function radiogroup( $d ) {
		if ( !isset( $d['name'] ) ) {
			$d['name'] = $d['id'];
		}

		foreach ( $d['values'] as $i ) {
			echo '<label><input type="radio" name="' . esc_attr( $d['name'] ) . '"';
			if ( isset( $d['class'] ) ) {
				echo ' class="' . esc_attr( $d['class'] ) . '"';
			}
			echo ' value="' . esc_attr( $i['value'] ) . '"' .
					checked( $d['value'], $i['value'] ) . '>';
			echo esc_html( $i['name'] );
			echo '</label>';
			echo '<br />';
		}
	}



	static public function checkboxes( $d ) {
		$first = true;

		foreach ($d as $c) {
			if ( !$first ) {
				echo '<br />';
			} else {
				$first = false;
			}

			AdminUi::checkbox( $c );
		}
	}



	static public function tr_textbox( $d ) {
		echo '<tr>';
		AdminUi::th( array( 'id' => $d['id'], 'label' => $d['name'] ) );
		echo '<td>';

		echo '<input name="' . esc_attr( $d['id'] ) . '" id="' .
			esc_attr( $d['id'] ) . '" class="regular-text" value="' .
			esc_attr( $d['value'] ) . '">';

		if ( isset( $d['description'] ) ) {
			AdminUi::description( $d['description'] );
		}
		echo '</td>';
		echo '</tr>';
	}



	static public function tr_checkbox( $row_label, $checkbox ) {
		echo '<tr>';
		AdminUi::th( array( 'id' => $checkbox['id'], 'label' => $row_label ) );
		echo '<td>';
		AdminUi::fieldset_start( $row_label );
		AdminUi::checkbox( $checkbox );
		AdminUi::fieldset_end();

		echo '</td>';
		echo '</tr>';
	}



	static public function tr_checkboxes( $row_label, $checkboxes ) {
		echo '<tr>';
		AdminUi::th( array( 'label' => $row_label ) );
		echo '<td>';
		AdminUi::fieldset_start( $row_label );
		AdminUi::checkboxes( $checkboxes );
		AdminUi::fieldset_end();
		echo '</td>';
		echo '</tr>';
	}



	static public function tr_radiogroup( $row_label, $d ) {
		echo '<tr>';
		AdminUi::th( array( 'label' => $row_label ) );
		echo '<td>';
		AdminUi::fieldset_start( $row_label );
		AdminUi::radiogroup( $d );
		AdminUi::fieldset_end();

		if ( isset( $d['description'] ) ) {
			AdminUi::description( $d['description'] );
		}
		echo '</td>';
		echo '</tr>';
	}
}
