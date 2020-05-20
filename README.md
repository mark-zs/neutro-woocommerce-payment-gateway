# neutro-woocommerce-payment-gateway
Neutro WooCommerce payment gateway plugin

# Development setup
```
docker-compose up -d
```

* Importing database:
```
docker exec -i neutro-woocommerce-payment-gateway_db_1 mysql -u neutro_woocommerce_payment_gateway -p1 neutro_woocommerce_payment_gateway < db.sql
```
For more details, check `./docker-compose.yml`