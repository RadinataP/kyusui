package com.kyusui.app.ui.customer.products;

import com.kyusui.app.domain.repository.CustomerRepository;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;

@ScopeMetadata
@QualifierMetadata
@DaggerGenerated
@Generated(
    value = "dagger.internal.codegen.ComponentProcessor",
    comments = "https://dagger.dev"
)
@SuppressWarnings({
    "unchecked",
    "rawtypes",
    "KotlinInternal",
    "KotlinInternalInJava"
})
public final class ProductListViewModel_Factory implements Factory<ProductListViewModel> {
  private final Provider<CustomerRepository> customerRepositoryProvider;

  public ProductListViewModel_Factory(Provider<CustomerRepository> customerRepositoryProvider) {
    this.customerRepositoryProvider = customerRepositoryProvider;
  }

  @Override
  public ProductListViewModel get() {
    return newInstance(customerRepositoryProvider.get());
  }

  public static ProductListViewModel_Factory create(
      Provider<CustomerRepository> customerRepositoryProvider) {
    return new ProductListViewModel_Factory(customerRepositoryProvider);
  }

  public static ProductListViewModel newInstance(CustomerRepository customerRepository) {
    return new ProductListViewModel(customerRepository);
  }
}
