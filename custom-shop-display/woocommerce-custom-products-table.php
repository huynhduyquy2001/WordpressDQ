<table class="woocommerce-products-table">
    <thead>
        <tr>
            
            <th><?php esc_html_e( 'Image', 'your-theme-text-domain' ); ?></th>
            <th><?php esc_html_e( 'Product', 'your-theme-text-domain' ); ?></th>
            <th><?php esc_html_e( 'Price', 'your-theme-text-domain' ); ?></th>
            <th><?php esc_html_e( 'Sale Price', 'your-theme-text-domain' ); ?></th>
            <th><?php esc_html_e( 'Add to Cart', 'your-theme-text-domain' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php while ( have_posts() ) : ?>
            <?php the_post(); ?>
            <tr>
            <td>
                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="product-thumbnail">
                            <?php the_post_thumbnail( 'thumbnail' ); ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php the_title(); ?>
                </td>
                
                <td>
                    <?php echo wc_price( get_post_meta( get_the_ID(), '_regular_price', true ) ); ?>
                </td>
                <td>
                    <?php 
                    $sale_price = get_post_meta( get_the_ID(), '_sale_price', true );
                    if ( $sale_price ) {
                        echo wc_price( $sale_price );
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td>
                    <?php woocommerce_template_loop_add_to_cart(); ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
