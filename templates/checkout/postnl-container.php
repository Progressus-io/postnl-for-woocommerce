<?php
/**
 * Template for PostNL option in frontend checkout page.
 *
 * @package PostNLWooCommerce\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<tr class="postnl-co-tr postnl-co-tr-container">
	<td colspan="2">
		<div id="postnl_checkout_option" class="postnl_checkout_container">
			<div class="postnl_checkout_tab_container">
				<ul class="postnl_checkout_tab_list">
					<li class="active">
						<label for="postnl_delivery_day" class="postnl_checkout_tab">
							<span>Delivery Day</span>
							<i>$ 10</i>
							<input type="radio" name="postnl_option" id="postnl_delivery_day" class="postnl_option" value="delivery_day" />
						</label>
					</li>
					<li>
						<label for="postnl_dropoff_points" class="postnl_checkout_tab">
							<span>Dropoff Points</span>
							<i>$ 10</i>
							<input type="radio" name="postnl_option" id="postnl_dropoff_points" class="postnl_option" value="dropoff_points" />
						</label>
					</li>
				</ul>
			</div>
			<div class="postnl_checkout_content_container">
				<div class="postnl_content active" id="postnl_delivery_day_content">
					<ul class="postnl_delivery_day_list postnl_list">
						<li>
							<div class="list_title"><span>Wednesday</span></div>
							<ul class="postnl_sub_list">
								<li class="standard">
									<label class="postnl_sub_radio_label" for="postnl_delivery_day_wed_std">
										<input type="radio" id="postnl_delivery_day_wed_std" name="postnl_delivery_day" class="postnl_sub_radio" value="wed-standard" />
										<i>No charge</i>
										<span>15.45 - 18.15</span>
									</label>
								</li>
								<li class="evening">
									<label class="postnl_sub_radio_label" for="postnl_delivery_day_wed_eve">
										<input type="radio" id="postnl_delivery_day_wed_eve" name="postnl_delivery_day" class="postnl_sub_radio" value="wed-evening" />
										<i>$ 5</i>
										<span>17.30 - 22.00</span>
									</label>
								</li>
							</ul>
						</li>
						<li>
							<div class="list_title"><span>Thursday</span></div>
							<ul class="postnl_sub_list">
								<li class="standard">
									<label class="postnl_sub_radio_label" for="postnl_delivery_day_thu_std">
										<input type="radio" id="postnl_delivery_day_thu_std" name="postnl_delivery_day" class="postnl_sub_radio" value="thu-standard" />
										<i>No charge</i>
										<span>15.45 - 18.15</span>
									</label>
								</li>
								<li class="evening">
									<label class="postnl_sub_radio_label" for="postnl_delivery_day_thu_eve">
										<input type="radio" id="postnl_delivery_day_thu_eve" name="postnl_delivery_day" class="postnl_sub_radio" value="thu-evening" />
										<i>$ 6</i>
										<span>17.30 - 22.00</span>
									</label>
								</li>
							</ul>
						</li>
					</ul>
				</div>
				<div class="postnl_content" id="postnl_dropoff_points_content">
					<ul class="postnl_dropoff_points_list postnl_list">
						<li>
							<div class="list_title"><span>Point A</span></div>
							<ul class="postnl_sub_list">
								<li>
									<label class="postnl_sub_radio_label" for="postnl_dropoff_points_a_a">
										<input type="radio" id="postnl_dropoff_points_a_a" name="postnl_dropoff_points" class="postnl_sub_radio" value="Point A-A" />
										<i>Vanaf 15:00<br />15-08-2022</i>
										<span>Point A-A<br />Toon Opening</span>
									</label>
								</li>
								<li>
									<label class="postnl_sub_radio_label" for="postnl_dropoff_points_a_b">
										<input type="radio" id="postnl_dropoff_points_a_b" name="postnl_dropoff_points" class="postnl_sub_radio" value="Point A-B" />
										<i>Vanaf 15:00<br />15-08-2022</i>
										<span>Point A-B<br />Toon Opening</span>
									</label>
								</li>
							</ul>
						</li>
						<li>
							<div class="list_title"><span>Point B</span></div>
							<ul class="postnl_sub_list">
								<li>
									<label class="postnl_sub_radio_label" for="postnl_dropoff_points_b_a">
										<input type="radio" id="postnl_dropoff_points_b_a" name="postnl_dropoff_points" class="postnl_sub_radio" value="Point B-A" />
										<i>Vanaf 15:00<br />15-08-2022</i>
										<span>Point B-A<br />Toon Opening</span>
									</label>
								</li>
							</ul>
						</li>
					</ul>
				</div>
			</div>
		</div>
	</td>
</tr>
