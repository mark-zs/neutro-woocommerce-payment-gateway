# neutro-woocommerce-payment-gateway
Neutro WooCommerce payment gateway plugin.
In order to connect to the Neutro payment gateway you will need an API Key. This you can get by applying at: https://app.neutro.net/#/merchant/new
If you experience any problems, please contact Neutro at support@neutro.net

# Development setup
```
docker-compose up -d
```

* Importing database:
```
docker exec -i neutro-woocommerce-payment-gateway_db_1 mysql -u neutro_woocommerce_payment_gateway -p1 neutro_woocommerce_payment_gateway < db.sql
```
For more details, check `./docker-compose.yml`
